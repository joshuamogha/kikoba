<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\BuildLoanExport;
use App\Actions\LoanManagement\DisburseLoan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\StoreLoanRequest;
use App\Http\Resources\LoanManagement\LoanResource;
use App\Models\LoanManagement\Loan;
use App\Models\UserManagement\Group;
use App\Support\TableExport;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $query = $this->filteredQuery($request, $group, $canManage)->with(self::RELATIONS);

        $matching = $query->clone()->reorder()->toBase();
        $totals = [
            'total' => round((float) (clone $matching)->sum('total_payable'), 2),
            'paid' => round((float) (clone $matching)->sum('amount_paid'), 2),
            'balance' => round((float) (clone $matching)->sum('outstanding_balance'), 2),
        ];

        $rows = $query->paginate(15)->withQueryString();

        $approved = $canManage
            ? $group->loanApplications()->where('status', 'approved')->doesntHave('loan')->with('groupMember:id,name')->orderBy('id')->get()
                ->map(fn ($application) => ['value' => $application->id, 'label' => "{$application->application_number} - {$application->groupMember->name} (".number_format((float) $application->approved_amount, 2).')'])->all()
            : [];

        return LoanResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'totals' => $totals,
            'options' => [
                'approved_applications' => $approved,
                'export_columns' => TableExport::options(BuildLoanExport::columns()),
            ],
        ])->response();
    }

    /**
     * Download the loans the list shows (same search and status filters) with only the chosen columns, as Excel, CSV or a
     * print-ready page to save as PDF.
     */
    public function export(Request $request): BinaryFileResponse|StreamedResponse|View
    {
        $columns = BuildLoanExport::columns();
        $choices = TableExport::choices($request, array_keys($columns));
        $group = $this->groupFor($request);
        $loans = $this->filteredQuery($request, $group, Gate::allows('manageLoans', $group))->with(self::RELATIONS)->get();
        $status = $request->string('status')->toString();

        return TableExport::respond($choices['format'], 'loans-'.str($group->name)->slug(), $group->name, 'Loans', ($status === '' ? 'All statuses' : ucfirst($status)).' · '.$group->currency, [
            TableExport::table($loans, $columns, $choices['columns']),
        ]);
    }

    /**
     * The group's loans narrowed by the list filters (search and status). Members who cannot manage loans only ever see their own.
     *
     * @return HasMany<Loan, Group>
     */
    private function filteredQuery(Request $request, Group $group, bool $canManage): HasMany
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        return $group->loans()
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['active', 'overdue', 'completed'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))))
            ->orderByDesc('disbursement_date')
            ->orderByDesc('id');
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
