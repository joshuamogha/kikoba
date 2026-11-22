<?php

namespace App\Http\Controllers\ReportManagement;

use App\Actions\ReportManagement\BuildMemberStatement;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Support\TableExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberStatementController extends Controller
{
    use ScopesReports;

    /**
     * The page, or one member's statement for a financial cycle as JSON (or a file with format=xlsx, pdf or csv and the chosen sections and months). Leaders and the Treasurer can
     * choose any member; everyone else gets their own statement.
     */
    public function index(Request $request, BuildMemberStatement $build): View|JsonResponse|StreamedResponse|BinaryFileResponse
    {
        if (! $request->expectsJson() && ! TableExport::wanted($request)) {
            return view('backend.pages.report-management.member-statement', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $everyone = $this->canSeeEveryone($group);
        $ownId = $this->ownMemberId($request, $group);

        $memberId = $everyone ? ($request->integer('group_member_id') ?: $group->groupMembers()->orderBy('member_number')->value('id')) : $ownId;
        abort_if($memberId === null && ! $everyone, 403);

        $member = $memberId === null ? null : $group->groupMembers()->findOrFail($memberId);
        $cycles = $this->cyclesOf($group);
        $cycle = $this->chosenCycle($request, $group, $cycles);
        $statement = $member !== null && $cycle !== null ? $build->handle($member, $cycle) : null;

        $options = $statement === null ? [] : $this->options($statement['months']);

        if ($statement !== null && TableExport::wanted($request)) {
            $choices = TableExport::choices($request, array_column($options, 'value'));

            return $this->file($choices['format'], $choices['columns'], $group->name, $cycle->name, $statement);
        }

        return response()->json([
            'data' => $statement === null ? null : [
                'group' => ['name' => $group->name, 'currency' => $group->currency],
                'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'start_date' => $cycle->start_date->toDateString(), 'end_date' => $cycle->end_date->toDateString()],
                ...$statement,
                'export_columns' => $options,
            ],
            'cycles' => $this->cycleOptions($cycles),
            'members' => $everyone ? $this->allMemberOptions($group) : [],
            'can_choose_member' => $everyone,
        ]);
    }

    /**
     * What can be chosen for an export: the sections (shares, contributions, loans) and the months shown in the first two.
     *
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $months): array
    {
        return [
            ['value' => 'shares', 'label' => 'Section: Shares'],
            ['value' => 'contributions', 'label' => 'Section: Contributions'],
            ['value' => 'loans', 'label' => 'Section: Loans'],
            ...array_map(fn (array $month) => ['value' => "m:{$month['key']}", 'label' => $month['label']], $months),
        ];
    }

    /**
     * The statement as one file with a table for shares, contributions and loans. $chosen keeps only some sections and months
     * (all of them when null, or when none of that kind is chosen).
     *
     * @param  array<int, string>|null  $chosen
     * @param  array<string, mixed>  $statement
     */
    private function file(string $format, ?array $chosen, string $groupName, string $cycleName, array $statement): BinaryFileResponse|StreamedResponse|View
    {
        $member = $statement['member'];
        $chosen ??= array_column($this->options($statement['months']), 'value');
        $sections = array_values(array_intersect(['shares', 'contributions', 'loans'], $chosen)) ?: ['shares', 'contributions', 'loans'];
        $months = array_values(array_filter($statement['months'], fn (array $month) => in_array("m:{$month['key']}", $chosen, true))) ?: $statement['months'];
        $keep = array_flip(array_column($months, 'key'));
        $all = array_column($statement['months'], 'key');
        $pick = fn (array $values) => array_values(array_intersect_key(array_combine($all, array_values($values)), $keep));
        $headings = ['', ...array_column($months, 'label'), 'Total'];
        $tables = [];

        if (in_array('shares', $sections, true)) {
            $tables[] = ['title' => 'SHARES', 'headings' => $headings, 'totals' => null, 'rows' => [
                ['Shares', ...$pick(array_column($statement['shares']['rows'], 'shares')), $statement['shares']['total_shares']],
                ['Amount', ...$pick(array_column($statement['shares']['rows'], 'amount')), $statement['shares']['total_amount']],
            ]];
        }

        if (in_array('contributions', $sections, true)) {
            $tables[] = ['title' => 'CONTRIBUTIONS', 'headings' => ['Fund', ...array_slice($headings, 1)], 'rows' => array_map(fn (array $fund) => [$fund['name'], ...$pick($fund['months']), $fund['total']], $statement['contributions']['funds']),
                'totals' => ['Total', ...$pick($statement['contributions']['totals']), $statement['contributions']['grand_total']]];
        }

        if (in_array('loans', $sections, true)) {
            $loans = $statement['loans'];
            $tables[] = ['title' => 'LOANS', 'headings' => ['Loan', 'Product', 'Disbursed', 'Maturity', 'Principal', 'Interest', 'Penalty', 'Total payable', 'Paid', 'Balance', 'Status'],
                'rows' => array_map(fn (array $loan) => [$loan['loan_number'], $loan['product'], $loan['disbursement_date'], $loan['maturity_date'], $loan['principal'], $loan['interest'], $loan['penalty'], $loan['total_payable'], $loan['paid'], $loan['balance'], $loan['status']], $loans['rows']),
                'totals' => ['Total', '', '', '', $loans['totals']['principal'], '', '', $loans['totals']['total_payable'], $loans['totals']['paid'], $loans['totals']['balance']]];
        }

        return TableExport::respond($format, 'member-statement-'.str($member['name'])->slug(), $groupName, 'Member statement', "{$member['name']} ({$member['member_number']}) · {$member['phone']} · {$cycleName}", $tables);
    }
}
