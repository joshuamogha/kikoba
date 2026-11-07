<?php

namespace App\Actions\PaymentManagement;

use App\Models\PaymentManagement\ContributionItem;
use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;
use Illuminate\Support\Carbon;

class BuildContributionCalendar
{
    public const TYPES = ['contributions', 'shares'];

    private const MAX_MONTHS = 24;

    /**
     * A members × months grid of what each member paid for each month of a financial cycle.
     *
     * A cell is `paid` (at least the expected amount), `short` (something, but less than expected), `missing` (the month has ended and
     * nothing was paid), `upcoming` (the month has not ended yet) or `n/a` (the member had not joined yet or had already left).
     * For contributions the expected amount is the minimum of the group's active mandatory funds; for shares any purchase counts.
     *
     * @param  string  $type  contributions or shares
     * @return array{months: array<int, array{key: string, label: string}>, expected: float, rows: array<int, array<string, mixed>>, totals: array<string, float>, grand_total: float}
     */
    public function handle(Group $group, FinancialCycle $cycle, string $type, ?int $onlyMemberId = null): array
    {
        $months = $this->months($cycle);
        $expected = $type === 'contributions'
            ? (float) $group->groupFunds()->where('status', 'active')->where('is_mandatory', true)->sum('minimum_contribution')
            : 0.0;
        $paid = $type === 'contributions' ? $this->contributionAmounts($group, $cycle, $onlyMemberId) : $this->shareAmounts($group, $cycle, $onlyMemberId);

        $members = $group->groupMembers()
            ->whereDate('joined_at', '<=', $cycle->end_date)
            ->where(fn ($query) => $query->whereNull('left_at')->orWhereDate('left_at', '>=', $cycle->start_date))
            ->when($onlyMemberId !== null, fn ($query) => $query->whereKey($onlyMemberId))
            ->orderBy('member_number')
            ->get(['id', 'name', 'member_number', 'joined_at', 'left_at']);

        $totals = array_fill_keys(array_column($months, 'key'), 0.0);
        $rows = [];

        foreach ($members as $member) {
            $cells = [];
            $summary = ['paid' => 0, 'short' => 0, 'missing' => 0];
            $total = 0.0;

            foreach ($months as $month) {
                $amount = (float) ($paid[$member->id][$month['key']]['amount'] ?? 0);
                $quantity = (int) ($paid[$member->id][$month['key']]['quantity'] ?? 0);
                $status = $this->status($member->joined_at, $member->left_at, $month['key'], $amount, $expected);

                $cells[$month['key']] = ['amount' => $amount, 'quantity' => $quantity, 'status' => $status];
                $totals[$month['key']] += $amount;
                $total += $amount;

                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
            }

            $rows[] = [
                'member_id' => $member->id,
                'name' => $member->name,
                'member_number' => $member->member_number,
                'cells' => $cells,
                'total' => $total,
                'months_paid' => $summary['paid'],
                'months_short' => $summary['short'],
                'months_missing' => $summary['missing'],
            ];
        }

        return ['months' => $months, 'expected' => $expected, 'rows' => $rows, 'totals' => $totals, 'grand_total' => array_sum($totals)];
    }

    /**
     * Every calendar month from the cycle's start to its end.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function months(FinancialCycle $cycle): array
    {
        $months = [];
        $month = $cycle->start_date->copy()->startOfMonth();

        while ($month->lte($cycle->end_date) && count($months) < self::MAX_MONTHS) {
            $months[] = ['key' => $month->format('Y-m'), 'label' => $month->format('M Y')];
            $month->addMonth();
        }

        return $months;
    }

    /**
     * Approved contribution amounts per member and month, keyed by the month paid for.
     *
     * @return array<int, array<string, array{amount: float, quantity: int}>>
     */
    private function contributionAmounts(Group $group, FinancialCycle $cycle, ?int $onlyMemberId): array
    {
        $amounts = [];

        ContributionItem::query()
            ->with('contribution:id,group_member_id,contribution_date')
            ->whereDoesntHave('groupFund.fundType', fn ($type) => $type->where('code', 'SHR'))
            ->whereHas('contribution', fn ($contribution) => $contribution
                ->where('group_id', $group->id)
                ->where('financial_cycle_id', $cycle->id)
                ->where('status', 'approved')
                ->when($onlyMemberId !== null, fn ($query) => $query->where('group_member_id', $onlyMemberId)))
            ->each(function (ContributionItem $item) use (&$amounts): void {
                $key = ($item->period_start ?? $item->contribution->contribution_date)->format('Y-m');
                $memberId = $item->contribution->group_member_id;

                $amounts[$memberId][$key]['amount'] = ($amounts[$memberId][$key]['amount'] ?? 0) + (float) $item->amount;
                $amounts[$memberId][$key]['quantity'] = 0;
            });

        return $amounts;
    }

    /**
     * Approved share purchases per member and month, keyed by the month paid for.
     *
     * @return array<int, array<string, array{amount: float, quantity: int}>>
     */
    private function shareAmounts(Group $group, FinancialCycle $cycle, ?int $onlyMemberId): array
    {
        $amounts = [];

        MemberShareTransaction::query()
            ->where('group_id', $group->id)
            ->where('financial_cycle_id', $cycle->id)
            ->where('status', 'approved')
            ->where('transaction_type', 'purchase')
            ->when($onlyMemberId !== null, fn ($query) => $query->where('group_member_id', $onlyMemberId))
            ->each(function (MemberShareTransaction $purchase) use (&$amounts): void {
                $key = ($purchase->period_start ?? $purchase->transaction_date)->format('Y-m');

                $amounts[$purchase->group_member_id][$key]['amount'] = ($amounts[$purchase->group_member_id][$key]['amount'] ?? 0) + (float) $purchase->total_amount;
                $amounts[$purchase->group_member_id][$key]['quantity'] = ($amounts[$purchase->group_member_id][$key]['quantity'] ?? 0) + $purchase->number_of_shares;
            });

        return $amounts;
    }

    private function status(mixed $joinedAt, mixed $leftAt, string $monthKey, float $amount, float $expected): string
    {
        if ($amount > 0) {
            return $expected > 0 && $amount < $expected ? 'short' : 'paid';
        }

        $start = Carbon::createFromFormat('!Y-m', $monthKey)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if (Carbon::parse($joinedAt)->gt($end) || ($leftAt !== null && Carbon::parse($leftAt)->lt($start))) {
            return 'n/a';
        }

        return $end->isFuture() ? 'upcoming' : 'missing';
    }
}
