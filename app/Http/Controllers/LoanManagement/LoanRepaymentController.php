<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\AllocateLoanRepayment;
use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\StoreLoanRepaymentRequest;
use App\Http\Resources\LoanManagement\LoanRepaymentResource;
use App\Models\LoanManagement\LoanRepayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanRepaymentController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['loan', 'groupMember', 'paymentMethod'];

    /**
     * Show the page, or the group's repayments as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-repayments', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageLoans', $group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = LoanRepayment::query()
            ->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'approved'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('repayment_number', 'like', "%{$search}%")
                ->orWhereHas('loan', fn ($loan) => $loan->where('loan_number', 'like', "%{$search}%"))
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return LoanRepaymentResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => $canManage ? [
                'loans' => $group->loans()->whereIn('status', ['active', 'overdue'])->with('groupMember:id,name')->orderBy('id')->get()
                    ->map(fn ($loan) => ['value' => $loan->id, 'label' => "{$loan->loan_number} - {$loan->groupMember->name} (owes ".number_format((float) $loan->outstanding_balance, 2).')'])->all(),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ] : null,
        ])->response();
    }

    public function store(StoreLoanRepaymentRequest $request): JsonResponse
    {
        $group = $request->group();
        $loan = $group->loans()->findOrFail($request->validated('loan_id'));
        $date = Carbon::parse($request->validated('payment_date'));

        $repayment = DB::transaction(function () use ($request, $loan, $date): LoanRepayment {
            $repayment = LoanRepayment::query()->create([
                ...$request->validated(),
                'group_member_id' => $loan->group_member_id,
                'repayment_number' => 'PENDING-'.uniqid(),
                'status' => 'pending',
                'received_by' => $request->user()->id,
            ]);
            $repayment->update(['repayment_number' => sprintf('LR-%d-%06d', $date->year, $repayment->id)]);

            return $repayment;
        });

        return $this->saved($repayment, 'Repayment recorded successfully.', 201);
    }

    public function approve(Request $request, LoanRepayment $loanRepayment, AllocateLoanRepayment $allocate, RecordPaymentTransaction $record): JsonResponse
    {
        Gate::authorize('manageLoans', $loanRepayment->loan->group);

        if ($loanRepayment->status !== 'pending') {
            return $this->failure('Only a pending repayment can be approved.');
        }

        if ((float) $loanRepayment->amount > (float) $loanRepayment->loan->outstanding_balance) {
            return $this->failure('The repayment is more than the loan\'s outstanding balance.');
        }

        DB::transaction(function () use ($request, $loanRepayment, $allocate, $record): void {
            $loanRepayment->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $allocate->handle($loanRepayment);
            $record->handle($loanRepayment, $request->user());
        });

        return $this->saved($loanRepayment, 'Repayment approved.');
    }

    private function saved(LoanRepayment $repayment, string $message, int $status = 200): JsonResponse
    {
        $repayment->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new LoanRepaymentResource($repayment))->resolve()], $status);
    }
}
