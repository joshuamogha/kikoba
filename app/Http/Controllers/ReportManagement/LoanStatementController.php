<?php

namespace App\Http\Controllers\ReportManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Support\ReportCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanStatementController extends Controller
{
    use ScopesReports;

    /**
     * Every loan of the group with what was paid and what is still owed (everyone's for leaders and the Treasurer, otherwise the
     * viewer's own), as a page, JSON or CSV (format=csv).
     */
    public function index(Request $request): View|JsonResponse|StreamedResponse
    {
        if (! $request->expectsJson() && $request->query('format') !== 'csv') {
            return view('backend.pages.report-management.loan-statement', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $status = $request->string('status')->toString();

        $loans = $group->loans()
            ->with(['groupMember:id,name,member_number', 'loanProduct:id,name'])
            ->when(! $this->canSeeEveryone($group), fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['active', 'overdue', 'completed'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('disbursement_date')
            ->orderBy('id')
            ->get();

        $rows = $loans->map(fn ($loan) => [
            'loan_number' => $loan->loan_number,
            'member' => $loan->groupMember->name,
            'member_number' => $loan->groupMember->member_number,
            'product' => $loan->loanProduct->name,
            'disbursement_date' => $loan->disbursement_date->toDateString(),
            'maturity_date' => $loan->maturity_date?->toDateString(),
            'principal' => (float) $loan->principal_amount,
            'interest' => (float) $loan->interest_amount,
            'penalty' => (float) $loan->penalty_amount,
            'total_payable' => (float) $loan->total_payable,
            'paid' => (float) $loan->amount_paid,
            'balance' => (float) $loan->outstanding_balance,
            'status' => $loan->status,
        ])->all();

        $totals = [
            'principal' => (float) $loans->sum('principal_amount'),
            'interest' => (float) $loans->sum('interest_amount'),
            'penalty' => (float) $loans->sum('penalty_amount'),
            'total_payable' => (float) $loans->sum('total_payable'),
            'paid' => (float) $loans->sum('amount_paid'),
            'balance' => (float) $loans->sum('outstanding_balance'),
        ];

        if ($request->query('format') === 'csv') {
            return ReportCsv::download('loan-statement-'.str($group->name)->slug().'.csv', [
                [$group->name, 'Loan statement', $status !== '' ? $status : 'all loans', 'Printed', now()->toDateString()],
                [],
                ['Loan', 'Number', 'Member', 'Product', 'Disbursed', 'Maturity', 'Principal', 'Interest', 'Penalty', 'Total payable', 'Paid', 'Balance', 'Status'],
                ...array_map(fn (array $row) => [$row['loan_number'], $row['member_number'], $row['member'], $row['product'], $row['disbursement_date'], $row['maturity_date'], $row['principal'], $row['interest'], $row['penalty'], $row['total_payable'], $row['paid'], $row['balance'], $row['status']], $rows),
                ['Total', '', '', '', '', '', $totals['principal'], $totals['interest'], $totals['penalty'], $totals['total_payable'], $totals['paid'], $totals['balance']],
            ]);
        }

        return response()->json(['data' => ['group' => ['name' => $group->name, 'currency' => $group->currency], 'rows' => $rows, 'totals' => $totals]]);
    }
}
