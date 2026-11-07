<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\DisburseLoan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\StoreLoanRequest;
use App\Http\Resources\LoanManagement\LoanResource;
use App\Models\LoanManagement\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupMember', 'loanProduct'];

    /**
     * Show the page, or the group's loans as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loans', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageLoans', $group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->loans()
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['active', 'overdue', 'completed'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))))
            ->orderByDesc('disbursement_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $approved = $canManage
            ? $group->loanApplications()->where('status', 'approved')->doesntHave('loan')->with('groupMember:id,name')->orderBy('id')->get()
                ->map(fn ($application) => ['value' => $application->id, 'label' => "{$application->application_number} - {$application->groupMember->name} (".number_format((float) $application->approved_amount, 2).')'])->all()
            : [];

        return LoanResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => ['approved_applications' => $approved],
        ])->response();
    }

    /**
     * Disburse an approved application: create the loan and its installment schedule.
     */
    public function store(StoreLoanRequest $request, DisburseLoan $disburse): JsonResponse
    {
        $application = $request->group()->loanApplications()->findOrFail($request->validated('loan_application_id'));
        $loan = $disburse->handle($application, $request->user(), Carbon::parse($request->validated('disbursement_date')));
        $loan->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => 'Loan disbursed successfully.', 'data' => (new LoanResource($loan))->resolve()], 201);
    }

    /**
     * One loan with its schedule and repayments.
     */
    public function show(Request $request, Loan $loan): JsonResponse
    {
        Gate::authorize('view', $loan->group);

        abort_unless(
            Gate::allows('manageLoans', $loan->group) || $loan->groupMember->user_id === $request->user()->id,
            403,
        );

        $loan->load([...self::RELATIONS, 'loanInstallments' => fn ($query) => $query->orderBy('installment_number'), 'loanRepayments' => fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id')]);

        return (new LoanResource($loan))->response();
    }
}
