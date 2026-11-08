<?php

namespace App\Actions\ReportManagement;

use App\Actions\PaymentManagement\BuildContributionCalendar;
use App\Models\PaymentManagement\ContributionItem;
use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\GroupMember;
use Illuminate\Support\Carbon;

class BuildMemberStatement
{
    public function __construct(private BuildContributionCalendar $calendar) {}

    /**
     * A member's statement for one financial cycle: shares for each month with totals, contributions per fund and month (the
     * Shares fund is left out, it is in the shares section), and the member's whole loan history with balances.
     *
     * @return array{member: array<string, mixed>, months: array<int, array{key: string, label: string}>, shares: array<string, mixed>, contributions: array<string, mixed>, loans: array<string, mixed>}
     */
    public function handle(GroupMember $member, FinancialCycle $cycle): array
    {
        $months = $this->calendar->months($cycle);

        return [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'member_number' => $member->member_number,
                'phone' => $member->phone_number,
                'joined_at' => $member->joined_at ? Carbon::parse($member->joined_at)->toDateString() : null,
            ],
            'months' => $months,
            'shares' => $this->shares($member, $cycle, $months),
            'contributions' => $this->contributions($member, $cycle, $months),
            'loans' => $this->loans($member),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array{rows: array<int, array{key: string, label: string, shares: int, amount: float}>, total_shares: int, total_amount: float}
     */
    private function shares(GroupMember $member, FinancialCycle $cycle, array $months): array
    {
        $byMonth = [];

        MemberShareTransaction::query()
            ->where('group_member_id', $member->id)
            ->where('financial_cycle_id', $cycle->id)
            ->where('status', 'approved')
            ->where('transaction_type', 'purchase')
            ->each(function (MemberShareTransaction $purchase) use (&$byMonth): void {
                $key = ($purchase->period_start ?? $purchase->transaction_date)->format('Y-m');

                $byMonth[$key]['shares'] = ($byMonth[$key]['shares'] ?? 0) + $purchase->number_of_shares;
                $byMonth[$key]['amount'] = ($byMonth[$key]['amount'] ?? 0) + (float) $purchase->total_amount;
            });

        $rows = array_map(fn (array $month) => [
            'key' => $month['key'],
            'label' => $month['label'],
            'shares' => (int) ($byMonth[$month['key']]['shares'] ?? 0),
            'amount' => (float) ($byMonth[$month['key']]['amount'] ?? 0),
        ], $months);

        return ['rows' => $rows, 'total_shares' => array_sum(array_column($rows, 'shares')), 'total_amount' => array_sum(array_column($rows, 'amount'))];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array{funds: array<int, array{id: int, name: string, months: array<string, float>, total: float}>, totals: array<string, float>, grand_total: float}
     */
    private function contributions(GroupMember $member, FinancialCycle $cycle, array $months): array
    {
        $funds = [];

        ContributionItem::query()
            ->with(['groupFund:id,name', 'contribution:id,contribution_date'])
            ->whereDoesntHave('groupFund.fundType', fn ($type) => $type->where('code', 'SHR'))
            ->whereHas('contribution', fn ($contribution) => $contribution
                ->where('group_member_id', $member->id)
                ->where('financial_cycle_id', $cycle->id)
                ->where('status', 'approved'))
            ->each(function (ContributionItem $item) use (&$funds): void {
                $key = ($item->period_start ?? $item->contribution->contribution_date)->format('Y-m');

                $funds[$item->group_fund_id]['name'] = $item->groupFund->name;
                $funds[$item->group_fund_id]['months'][$key] = ($funds[$item->group_fund_id]['months'][$key] ?? 0) + (float) $item->amount;
            });

        $totals = array_fill_keys(array_column($months, 'key'), 0.0);
        $rows = [];

        foreach ($funds as $fundId => $fund) {
            $cells = [];

            foreach ($months as $month) {
                $cells[$month['key']] = (float) ($fund['months'][$month['key']] ?? 0);
                $totals[$month['key']] += $cells[$month['key']];
            }

            $rows[] = ['id' => $fundId, 'name' => $fund['name'], 'months' => $cells, 'total' => array_sum($cells)];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return ['funds' => $rows, 'totals' => $totals, 'grand_total' => array_sum($totals)];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    private function loans(GroupMember $member): array
    {
        $loans = $member->loans()->with('loanProduct:id,name')->orderBy('disbursement_date')->orderBy('id')->get();

        return [
            'rows' => $loans->map(fn ($loan) => [
                'loan_number' => $loan->loan_number,
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
            ])->all(),
            'totals' => [
                'principal' => (float) $loans->sum('principal_amount'),
                'total_payable' => (float) $loans->sum('total_payable'),
                'paid' => (float) $loans->sum('amount_paid'),
                'balance' => (float) $loans->sum('outstanding_balance'),
            ],
        ];
    }
}
