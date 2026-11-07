<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\RecordExistingLoan;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\ApproveLoanApplicationRequest;
use App\Http\Requests\LoanManagement\PreviewLoanApplicationRequest;
use App\Http\Requests\LoanManagement\RecordLoanRequest;
use App\Http\Requests\LoanManagement\RejectLoanApplicationRequest;
use App\Http\Requests\LoanManagement\StoreLoanApplicationRequest;
use App\Http\Resources\LoanManagement\LoanApplicationResource;
use App\Models\LoanManagement\LoanApplication;
use App\Models\LoanManagement\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanApplicationController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupMember', 'loanProduct.repaymentFrequency', 'loanGuarantors', 'loan:id,loan_application_id,maturity_date'];

    /**
     * Show the page, or the group's applications as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-applications', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageLoans', $group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->loanApplications()
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['submitted', 'approved', 'rejected', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('application_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))))
            ->orderByDesc('application_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $ownMemberId = $group->groupMembers()->where('user_id', $request->user()->id)->where('membership_status', 'active')->value('id');

        return LoanApplicationResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => [
                'members' => $canManage ? $this->memberOptions($group) : array_values(array_filter($this->memberOptions($group), fn (array $member) => $member['value'] === $ownMemberId)),
                'products' => $group->loanProducts()->where('status', 'active')->with('loanProductRanges')->orderBy('name')->get()->map(fn ($product) => [
                    'value' => $product->id,
                    'label' => $product->name,
                    'repayment_mode' => $product->repayment_mode,
                    'ranges' => $product->loanProductRanges->map(fn ($range) => [
                        'minimum_amount' => (float) $range->minimum_amount,
                        'maximum_amount' => $range->maximum_amount === null ? null : (float) $range->maximum_amount,
                        'duration_months' => $range->duration_months,
                    ])->all(),
                ])->all(),
                'own_member_id' => $ownMemberId,
            ],
        ])->response();
    }

    /**
     * The term and the date the loan would end for an amount, before anything is saved. Ranged products take the term from the amount;
     * other products from the duration sent.
     */
    public function preview(PreviewLoanApplicationRequest $request): JsonResponse
    {
        $product = LoanProduct::query()->with('repaymentFrequency')->findOrFail($request->validated('loan_product_id'));
        $amount = (float) $request->validated('amount');
        $months = $product->hasRanges() ? $product->termFor($amount) : $request->integer('duration_months');

        if (! $months) {
            return response()->json(['data' => ['term_months' => null, 'end_date' => null, 'repayment_mode' => $product->repayment_mode, 'message' => $product->hasRanges() ? 'No loan range covers this amount.' : 'Enter the duration.']]);
        }

        $from = Carbon::parse($request->validated('disbursement_date') ?? today());

        return response()->json(['data' => [
            'term_months' => $months,
            'end_date' => $product->endDateFor($months, $from)->toDateString(),
            'repayment_mode' => $product->repayment_mode,
            'message' => null,
        ]]);
    }

    public function store(StoreLoanApplicationRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $group = $request->group();
        $product = $group->loanProducts()->findOrFail($request->validated('loan_product_id'));

        $application = DB::transaction(function () use ($request, $group, $product, $resolveCycle): LoanApplication {
            $application = $group->loanApplications()->create([
                ...$request->validated(),
                'duration_months' => $product->termFor((float) $request->validated('requested_amount')) ?? $request->validated('duration_months'),
                'financial_cycle_id' => $resolveCycle->handle($group, now(), $request->user()->id)->id,
                'application_number' => 'PENDING-'.uniqid(),
                'interest_rate' => $product->interest_rate,
                'loan_interest_method_id' => $product->loan_interest_method_id,
                'application_date' => now()->toDateString(),
                'status' => 'submitted',
            ]);
            $application->update(['application_number' => sprintf('LA-%d-%06d', now()->year, $application->id)]);

            return $application;
        });

        return $this->saved($application, 'Loan application submitted.', 201);
    }

    /**
     * Record a loan that has already happened in one step: the application (approved), its guarantors (accepted) and the loan with
     * its installment schedule, all dated as given.
     */
    public function record(RecordLoanRequest $request, RecordExistingLoan $recordLoan): JsonResponse
    {
        $group = $request->group();

        [$application, $loan] = $recordLoan->handle(
            $group,
            $group->loanProducts()->findOrFail($request->validated('loan_product_id')),
            $request->user(),
            $request->validated(),
            Carbon::parse($request->validated('application_date')),
            Carbon::parse($request->validated('disbursement_date')),
        );

        return response()->json([
            'success' => true,
            'message' => "Loan {$loan->loan_number} recorded from application {$application->application_number}.",
            'data' => ['application_id' => $application->id, 'loan_id' => $loan->id, 'loan_number' => $loan->loan_number, 'application_number' => $application->application_number],
        ], 201);
    }

    public function approve(ApproveLoanApplicationRequest $request, LoanApplication $loanApplication): JsonResponse
    {
        if ($loanApplication->status !== 'submitted') {
            return $this->failure('Only a submitted application can be approved.');
        }

        $required = $loanApplication->loanProduct->guarantors_required;

        if ($loanApplication->loanGuarantors()->where('status', 'accepted')->count() < $required) {
            return $this->failure("This product needs {$required} accepted guarantor(s) before approval.");
        }

        $maximum = $loanApplication->loanProduct->maximum_amount;

        if ($maximum !== null && (float) $request->validated('approved_amount') > (float) $maximum) {
            return $this->failure('The approved amount is above the product maximum.');
        }

        $product = $loanApplication->loanProduct;
        $term = $product->hasRanges() ? $product->termFor((float) $request->validated('approved_amount')) : $loanApplication->duration_months;

        if ($term === null) {
            return $this->failure('No loan range covers the approved amount.');
        }

        $loanApplication->update([
            'status' => 'approved',
            'duration_months' => $term,
            'approved_amount' => $request->validated('approved_amount'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return $this->saved($loanApplication, 'Application approved.');
    }

    public function reject(RejectLoanApplicationRequest $request, LoanApplication $loanApplication): JsonResponse
    {
        if ($loanApplication->status !== 'submitted') {
            return $this->failure('Only a submitted application can be rejected.');
        }

        $loanApplication->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return $this->saved($loanApplication, 'Application rejected.');
    }

    public function cancel(Request $request, LoanApplication $loanApplication): JsonResponse
    {
        abort_unless(
            $loanApplication->groupMember->user_id === $request->user()->id || Gate::allows('manageLoans', $loanApplication->group),
            403,
        );

        if ($loanApplication->status !== 'submitted') {
            return $this->failure('Only a submitted application can be cancelled.');
        }

        $loanApplication->update(['status' => 'cancelled']);

        return $this->saved($loanApplication, 'Application cancelled.');
    }

    private function saved(LoanApplication $application, string $message, int $status = 200): JsonResponse
    {
        $application->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new LoanApplicationResource($application))->resolve()], $status);
    }
}
