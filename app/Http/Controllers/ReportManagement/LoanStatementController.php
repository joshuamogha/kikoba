<?php

namespace App\Http\Controllers\ReportManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Support\TableExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanStatementController extends Controller
{
    use ScopesReports;

    /**
     * The columns of the statement as [label, is an amount], by the key of the row.
     */
    private const COLUMNS = [
        'loan_number' => ['Loan number'], 'member_number' => ['Member number'], 'member' => ['Member'], 'product' => ['Product'],
        'disbursement_date' => ['Disbursed'], 'maturity_date' => ['Maturity'], 'principal' => ['Principal', true], 'interest' => ['Interest', true],
        'penalty' => ['Penalty', true], 'total_payable' => ['Total payable', true], 'paid' => ['Paid', true], 'balance' => ['Balance', true], 'status' => ['Status'],
    ];

    /**
     * Every loan of the group with what was paid and what is still owed (everyone's for leaders and the Treasurer, otherwise the
     * viewer's own), as a page, JSON, or a file (format=xlsx, pdf or csv, with the chosen columns).
     */
    public function index(Request $request): View|JsonResponse|StreamedResponse|BinaryFileResponse
    {
        if (! $request->expectsJson() && ! TableExport::wanted($request)) {
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

        $columns = TableExport::arrayColumns(self::COLUMNS);

        if (TableExport::wanted($request)) {
            $choices = TableExport::choices($request, array_keys($columns));

            return TableExport::respond($choices['format'], 'loan-statement-'.str($group->name)->slug(), $group->name, 'Loan statement', ($status !== '' ? ucfirst($status) : 'All loans').' · '.$group->currency, [
                TableExport::table($rows, $columns, $choices['columns']),
            ]);
        }

        return response()->json(['data' => ['group' => ['name' => $group->name, 'currency' => $group->currency], 'rows' => $rows, 'totals' => $totals, 'export_columns' => TableExport::options($columns)]]);
    }
}
