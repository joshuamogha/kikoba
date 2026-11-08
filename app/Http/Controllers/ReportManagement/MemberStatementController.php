<?php

namespace App\Http\Controllers\ReportManagement;

use App\Actions\ReportManagement\BuildMemberStatement;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Support\ReportCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberStatementController extends Controller
{
    use ScopesReports;

    /**
     * The page, or one member's statement for a financial cycle as JSON (or CSV with format=csv). Leaders and the Treasurer can
     * choose any member; everyone else gets their own statement.
     */
    public function index(Request $request, BuildMemberStatement $build): View|JsonResponse|StreamedResponse
    {
        if (! $request->expectsJson() && $request->query('format') !== 'csv') {
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

        if ($request->query('format') === 'csv' && $statement !== null) {
            return $this->csv($group->name, $cycle->name, $statement);
        }

        return response()->json([
            'data' => $statement === null ? null : [
                'group' => ['name' => $group->name, 'currency' => $group->currency],
                'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'start_date' => $cycle->start_date->toDateString(), 'end_date' => $cycle->end_date->toDateString()],
                ...$statement,
            ],
            'cycles' => $this->cycleOptions($cycles),
            'members' => $everyone ? $this->allMemberOptions($group) : [],
            'can_choose_member' => $everyone,
        ]);
    }

    /**
     * The statement as one CSV file with a section for shares, contributions and loans.
     *
     * @param  array<string, mixed>  $statement
     */
    private function csv(string $groupName, string $cycleName, array $statement): StreamedResponse
    {
        $member = $statement['member'];
        $months = $statement['months'];
        $rows = [
            [$groupName, 'Member statement'],
            ['Member', $member['name'], 'Number', $member['member_number'], 'Phone', $member['phone']],
            ['Financial cycle', $cycleName, 'Printed', now()->toDateString()],
            [],
            ['SHARES'],
            ['', ...array_column($months, 'label'), 'Total'],
            ['Shares', ...array_column($statement['shares']['rows'], 'shares'), $statement['shares']['total_shares']],
            ['Amount', ...array_column($statement['shares']['rows'], 'amount'), $statement['shares']['total_amount']],
            [],
            ['CONTRIBUTIONS'],
            ['Fund', ...array_column($months, 'label'), 'Total'],
            ...array_map(fn (array $fund) => [$fund['name'], ...array_values($fund['months']), $fund['total']], $statement['contributions']['funds']),
            ['Total', ...array_values($statement['contributions']['totals']), $statement['contributions']['grand_total']],
            [],
            ['LOANS'],
            ['Loan', 'Product', 'Disbursed', 'Maturity', 'Principal', 'Interest', 'Penalty', 'Total payable', 'Paid', 'Balance', 'Status'],
            ...array_map(fn (array $loan) => [$loan['loan_number'], $loan['product'], $loan['disbursement_date'], $loan['maturity_date'], $loan['principal'], $loan['interest'], $loan['penalty'], $loan['total_payable'], $loan['paid'], $loan['balance'], $loan['status']], $statement['loans']['rows']),
            ['Total', '', '', '', $statement['loans']['totals']['principal'], '', '', $statement['loans']['totals']['total_payable'], $statement['loans']['totals']['paid'], $statement['loans']['totals']['balance']],
        ];

        return ReportCsv::download('member-statement-'.str($member['name'])->slug().'.csv', $rows);
    }
}
