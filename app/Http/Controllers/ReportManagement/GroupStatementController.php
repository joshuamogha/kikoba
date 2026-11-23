<?php

namespace App\Http\Controllers\ReportManagement;

use App\Actions\ReportManagement\BuildGroupStatement;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Support\TableExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupStatementController extends Controller
{
    use ScopesReports;

    /**
     * The group's totals (shares, every fund, fines, loans) with a row for each member: everyone's for leaders and the Treasurer,
     * otherwise the viewer's own row. For all time, or one financial cycle. As a page, JSON, or a file (format=xlsx, pdf or csv,
     * with the chosen member columns).
     */
    public function index(Request $request, BuildGroupStatement $build): View|JsonResponse|StreamedResponse|BinaryFileResponse
    {
        if (! $request->expectsJson() && ! TableExport::wanted($request)) {
            return view('backend.pages.report-management.group-statement', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $onlyMemberId = $this->canSeeEveryone($group) ? null : ($this->ownMemberId($request, $group) ?? 0);
        $cycles = $this->cyclesOf($group);
        $cycle = $request->filled('financial_cycle_id') ? $group->financialCycles()->findOrFail($request->integer('financial_cycle_id')) : null;

        $statement = $build->handle($group, $cycle?->id, $onlyMemberId);
        $columns = $this->columns($statement['funds']);

        if (TableExport::wanted($request)) {
            $choices = TableExport::choices($request, array_keys($columns));

            return TableExport::respond($choices['format'], 'group-statement-'.str($group->name)->slug(), $group->name, 'Group statement', ($cycle?->name ?? 'All time').' · '.$group->currency, [
                $this->summarySection($statement),
                TableExport::table($statement['rows'], $columns, $choices['columns'], 'MEMBERS'),
            ]);
        }

        return response()->json([
            'data' => [
                'group' => ['name' => $group->name, 'currency' => $group->currency],
                'cycle' => $cycle === null ? null : ['id' => $cycle->id, 'name' => $cycle->name],
                ...$statement,
                'export_columns' => TableExport::options($columns),
            ],
            'cycles' => $this->cycleOptions($cycles),
        ]);
    }

    /**
     * The member columns: number and name, shares, one column per fund, fines and loans.
     *
     * @param  array<int, array{id: int, name: string}>  $funds
     * @return array<string, array{label: string, amount: bool, value: callable(mixed): (string|int|float|null)}>
     */
    private function columns(array $funds): array
    {
        $fundColumns = [];

        foreach ($funds as $fund) {
            $fundColumns["fund_{$fund['id']}"] = [$fund['name'], true];
        }

        return TableExport::arrayColumns([
            'member_number' => ['Number'], 'name' => ['Name'], 'shares' => ['Shares', true], 'shares_amount' => ['Shares amount', true],
            ...$fundColumns,
            'total_contributions' => ['Total contributions', true],
            'fines_issued' => ['Fines', true], 'fines_paid' => ['Fines paid', true], 'fines_balance' => ['Fines balance', true],
            'loans_principal' => ['Loans given', true], 'loans_total_payable' => ['Loans total payable', true], 'loans_paid' => ['Loans paid', true], 'loans_balance' => ['Loans balance', true],
        ]);
    }

    /**
     * The summary as a two-column table for the exported file.
     *
     * @param  array<string, mixed>  $statement
     * @return array{title: string, headings: array<int, string>, rows: array<int, array<int, string|float|int>>, totals: null}
     */
    private function summarySection(array $statement): array
    {
        $summary = $statement['summary'];
        $loans = $summary['loans'];

        return ['title' => 'SUMMARY', 'headings' => ['Item', 'Amount'], 'totals' => null, 'rows' => [
            ['Shares held', $summary['shares']],
            ['Shares amount', $summary['shares_amount']],
            ...array_map(fn (array $fund) => ["Fund: {$fund['name']}", $fund['amount']], $summary['funds']),
            ['Total contributions', $summary['total_contributions']],
            ['Fines issued', $summary['fines']['issued']],
            ['Fines paid', $summary['fines']['paid']],
            ['Fines balance', $summary['fines']['balance']],
            ['Loans given (principal)', $loans['principal']],
            ['Loans total payable', $loans['total_payable']],
            ['Loans paid', $loans['paid']],
            ['Loans balance', $loans['balance']],
            ['Loans (number)', $loans['count']],
            ...collect(['fines' => 'Fines', 'loan_interest' => 'Loan interest', 'loan_penalties' => 'Loan penalties', 'loans' => 'Loan profit (interest and penalties)', 'income' => 'Other income', 'gross' => 'Gross profit', 'expenses' => 'Expenses', 'net' => 'Net profit'])
                ->flatMap(fn (string $label, string $key) => [
                    ["Profit - {$label}: current", $statement['profit'][$key]['current']],
                    ["Profit - {$label}: expected", $statement['profit'][$key]['expected']],
                    ["Profit - {$label}: total", $statement['profit'][$key]['total']],
                ])->all(),
            ...array_map(fn (array $row) => ["Income - {$row['name']}", $row['amount']], $statement['profit']['income_categories']),
            ...array_map(fn (array $row) => ["Expense - {$row['name']} (approved and paid)", $row['spent']], $statement['profit']['expense_categories']),
            ['Shares fund minus share records', $statement['checks']['hisa_difference']],
        ]];
    }
}
