<?php

namespace App\Http\Controllers\ReportManagement;

use App\Actions\PaymentManagement\BuildContributionCalendar;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Models\UserManagement\Group;
use App\Support\ReportCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyGridReportController extends Controller
{
    use ScopesReports;

    /**
     * Members x months: shares bought each month, with total shares and total amount.
     */
    public function shares(Request $request, BuildContributionCalendar $build): View|JsonResponse|StreamedResponse
    {
        return $this->report($request, $build, 'shares');
    }

    /**
     * Members x months: what each member paid to one fund each month.
     */
    public function contributions(Request $request, BuildContributionCalendar $build): View|JsonResponse|StreamedResponse
    {
        return $this->report($request, $build, 'contributions');
    }

    private function report(Request $request, BuildContributionCalendar $build, string $type): View|JsonResponse|StreamedResponse
    {
        if (! $request->expectsJson() && $request->query('format') !== 'csv') {
            return view("backend.pages.report-management.monthly-{$type}", ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $everyone = $this->canSeeEveryone($group);
        $onlyMemberId = $everyone ? null : ($this->ownMemberId($request, $group) ?? 0);

        $cycles = $this->cyclesOf($group);
        $cycle = $this->chosenCycle($request, $group, $cycles);
        $funds = $type === 'contributions' ? $this->fundOptions($group) : [];
        $fundId = $type === 'contributions' ? ($request->integer('group_fund_id') ?: ($funds[0]['value'] ?? null)) : null;

        $grid = $cycle === null ? null : $build->handle($group, $cycle, $type, $onlyMemberId, $fundId);

        if ($grid !== null && $type === 'shares') {
            $grid = $this->withShareCounts($grid);
        }

        if ($grid !== null && $request->query('format') === 'csv') {
            return $this->csv($group, $cycle->name, $type, $grid, collect($funds)->firstWhere('value', $fundId)['label'] ?? null);
        }

        return response()->json([
            'data' => $grid === null ? null : [
                'group' => ['name' => $group->name, 'currency' => $group->currency],
                'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'start_date' => $cycle->start_date->toDateString(), 'end_date' => $cycle->end_date->toDateString()],
                'type' => $type,
                'fund_id' => $fundId,
                ...$grid,
            ],
            'cycles' => $this->cycleOptions($cycles),
            'funds' => $funds,
        ]);
    }

    /**
     * Add each member's total number of shares and the number of shares bought in each month.
     *
     * @param  array<string, mixed>  $grid
     * @return array<string, mixed>
     */
    private function withShareCounts(array $grid): array
    {
        $monthShares = array_fill_keys(array_column($grid['months'], 'key'), 0);

        $grid['rows'] = array_map(function (array $row) use (&$monthShares): array {
            $row['total_shares'] = 0;

            foreach ($row['cells'] as $key => $cell) {
                $row['total_shares'] += $cell['quantity'];
                $monthShares[$key] += $cell['quantity'];
            }

            return $row;
        }, $grid['rows']);

        $grid['month_shares'] = $monthShares;
        $grid['total_shares'] = array_sum($monthShares);

        return $grid;
    }

    /**
     * The funds a contributions report can be run for: every fund except share capital, which has its own report.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function fundOptions(Group $group): array
    {
        return $group->groupFunds()
            ->whereDoesntHave('fundType', fn ($type) => $type->where('code', 'SHR'))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($fund) => ['value' => $fund->id, 'label' => $fund->name])->all();
    }

    /**
     * @param  array<string, mixed>  $grid
     */
    private function csv(Group $group, string $cycleName, string $type, array $grid, ?string $fundName): StreamedResponse
    {
        $months = array_column($grid['months'], 'label');
        $title = $type === 'shares' ? 'Monthly shares' : 'Monthly contributions'.($fundName ? " - {$fundName}" : '');

        if ($type === 'shares') {
            $rows = [
                [$group->name, $title, $cycleName, 'Printed', now()->toDateString()],
                [],
                ['Number', 'Name', ...$months, 'Total shares', 'Total amount'],
                ...array_map(fn (array $row) => [$row['member_number'], $row['name'], ...array_map(fn (array $cell) => $cell['quantity'], array_values($row['cells'])), $row['total_shares'], $row['total']], $grid['rows']),
                ['', 'Total', ...array_values($grid['month_shares']), $grid['total_shares'], $grid['grand_total']],
            ];
        } else {
            $rows = [
                [$group->name, $title, $cycleName, 'Printed', now()->toDateString()],
                [],
                ['Number', 'Name', ...$months, 'Total'],
                ...array_map(fn (array $row) => [$row['member_number'], $row['name'], ...array_map(fn (array $cell) => $cell['amount'], array_values($row['cells'])), $row['total']], $grid['rows']),
                ['', 'Total', ...array_values($grid['totals']), $grid['grand_total']],
            ];
        }

        return ReportCsv::download(str($title)->slug().'-'.str($cycleName)->slug().'.csv', $rows);
    }
}
