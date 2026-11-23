<?php

namespace App\Actions\ReportManagement;

use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\ContributionItem;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;

class BuildGroupStatement
{
    /**
     * The group's position in one page, to compare with another system's statement: shares, what was paid into each fund (the Shares
     * fund and the Social fund among them), fines and loans, as a summary and a row for every member.
     *
     * The profit section is what the group earns besides contributions: paid fines, loan interest and loan penalties, and every saved
     * income, each as what is already received (current), what is still to come (expected) and the total (only approved repayments
     * count as received). Expenses that are approved or paid are subtracted, and drafts are expected: net profit is gross profit minus
     * expenses.
     *
     * Shares, contributions, fines and loans can be limited to one financial cycle. Fines and loans always show their current
     * paid and balance figures. Waived fines are left out. The Shares fund is compared with the share records: any difference is
     * reported in `checks`.
     *
     * @return array{funds: array<int, array{id: int, name: string, is_shares: bool}>, summary: array<string, mixed>, profit: array<string, mixed>, rows: array<int, array<string, mixed>>, checks: array{hisa_difference: float}}
     */
    public function handle(Group $group, ?int $cycleId = null, ?int $onlyMemberId = null): array
    {
        $funds = $group->groupFunds()->with('fundType:id,code')->orderBy('name')->get(['id', 'name', 'fund_type_id', 'code'])
            ->map(fn ($fund) => ['id' => $fund->id, 'name' => $fund->name, 'is_shares' => $fund->fundType?->code === 'SHR'])->all();

        $shares = MemberShareTransaction::query()
            ->where('group_id', $group->id)->where('status', 'approved')->where('transaction_type', 'purchase')
            ->when($cycleId !== null, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->selectRaw('group_member_id, coalesce(sum(number_of_shares), 0) as shares, coalesce(sum(total_amount), 0) as amount')
            ->groupBy('group_member_id')->get()->keyBy('group_member_id');

        $contributions = ContributionItem::query()
            ->join('contributions', 'contributions.id', '=', 'contribution_items.contribution_id')
            ->where('contributions.group_id', $group->id)->where('contributions.status', 'approved')
            ->when($cycleId !== null, fn ($query) => $query->where('contributions.financial_cycle_id', $cycleId))
            ->selectRaw('contributions.group_member_id as member_id, contribution_items.group_fund_id as fund_id, sum(contribution_items.amount) as total')
            ->groupBy('contributions.group_member_id', 'contribution_items.group_fund_id')->get();

        $fines = $group->memberFines()->where('status', '!=', 'waived')
            ->when($cycleId !== null, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->selectRaw('group_member_id, coalesce(sum(amount), 0) as issued, coalesce(sum(amount_paid), 0) as paid, coalesce(sum(balance), 0) as balance')
            ->groupBy('group_member_id')->get()->keyBy('group_member_id');

        $loans = $group->loans()
            ->when($cycleId !== null, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->selectRaw('group_member_id, count(*) as loans, coalesce(sum(principal_amount), 0) as principal, coalesce(sum(total_payable), 0) as total_payable, coalesce(sum(amount_paid), 0) as paid, coalesce(sum(outstanding_balance), 0) as balance')
            ->groupBy('group_member_id')->get()->keyBy('group_member_id');

        $received = LoanRepayment::query()
            ->where('status', 'approved')
            ->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id)->when($cycleId !== null, fn ($query) => $query->where('financial_cycle_id', $cycleId)))
            ->selectRaw('coalesce(sum(interest_amount), 0) as interest, coalesce(sum(penalty_amount), 0) as penalty')->first();
        $charged = $group->loans()->when($cycleId !== null, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->selectRaw('coalesce(sum(interest_amount), 0) as interest, coalesce(sum(penalty_amount), 0) as penalty')->first();

        $incomes = $group->groupIncomes()
            ->join('income_categories', 'income_categories.id', '=', 'group_incomes.income_category_id')
            ->when($cycleId !== null, fn ($query) => $query->where('group_incomes.financial_cycle_id', $cycleId))
            ->selectRaw('income_categories.name as name, sum(group_incomes.amount) as amount')
            ->groupBy('income_categories.name')->orderBy('income_categories.name')->get()
            ->map(fn ($row) => ['name' => $row->name, 'amount' => round((float) $row->amount, 2)])->all();

        $expenses = $group->groupExpenses()
            ->join('group_expense_categories', 'group_expense_categories.id', '=', 'group_expenses.group_expense_category_id')
            ->when($cycleId !== null, fn ($query) => $query->where('group_expenses.financial_cycle_id', $cycleId))
            ->selectRaw("group_expense_categories.name as name, coalesce(sum(case when group_expenses.status in ('approved', 'paid') then group_expenses.amount else 0 end), 0) as spent, coalesce(sum(case when group_expenses.status = 'draft' then group_expenses.amount else 0 end), 0) as planned")
            ->groupBy('group_expense_categories.name')->orderBy('group_expense_categories.name')->get()
            ->map(fn ($row) => ['name' => $row->name, 'spent' => round((float) $row->spent, 2), 'planned' => round((float) $row->planned, 2)])->all();

        $rows = [];

        foreach ($group->groupMembers()->when($onlyMemberId !== null, fn ($query) => $query->whereKey($onlyMemberId))->orderBy('member_number')->get(['id', 'name', 'member_number']) as $member) {
            $row = [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
                'name' => $member->name,
                'shares' => (int) ($shares[$member->id]->shares ?? 0),
                'shares_amount' => (float) ($shares[$member->id]->amount ?? 0),
            ];

            foreach ($funds as $fund) {
                $row["fund_{$fund['id']}"] = (float) $contributions->where('member_id', $member->id)->where('fund_id', $fund['id'])->sum('total');
            }

            $row['total_contributions'] = array_sum(array_map(fn (array $fund) => $row["fund_{$fund['id']}"], $funds));
            $row['fines_issued'] = (float) ($fines[$member->id]->issued ?? 0);
            $row['fines_paid'] = (float) ($fines[$member->id]->paid ?? 0);
            $row['fines_balance'] = (float) ($fines[$member->id]->balance ?? 0);
            $row['loans_count'] = (int) ($loans[$member->id]->loans ?? 0);
            $row['loans_principal'] = (float) ($loans[$member->id]->principal ?? 0);
            $row['loans_total_payable'] = (float) ($loans[$member->id]->total_payable ?? 0);
            $row['loans_paid'] = (float) ($loans[$member->id]->paid ?? 0);
            $row['loans_balance'] = (float) ($loans[$member->id]->balance ?? 0);

            $rows[] = $row;
        }

        $sum = fn (string $key): float => round((float) array_sum(array_column($rows, $key)), 2);
        $fundTotals = array_map(fn (array $fund) => [...$fund, 'amount' => $sum("fund_{$fund['id']}")], $funds);
        $hisaFund = round((float) array_sum(array_column(array_filter($fundTotals, fn (array $fund) => $fund['is_shares']), 'amount')), 2);

        $statuses = $group->loans()->when($cycleId !== null, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'funds' => $funds,
            'summary' => [
                'shares' => (int) $sum('shares'),
                'shares_amount' => $sum('shares_amount'),
                'funds' => $fundTotals,
                'total_contributions' => $sum('total_contributions'),
                'fines' => ['issued' => $sum('fines_issued'), 'paid' => $sum('fines_paid'), 'balance' => $sum('fines_balance')],
                'loans' => [
                    'count' => (int) $sum('loans_count'), 'principal' => $sum('loans_principal'), 'total_payable' => $sum('loans_total_payable'),
                    'paid' => $sum('loans_paid'), 'balance' => $sum('loans_balance'),
                    'active' => (int) ($statuses['active'] ?? 0), 'overdue' => (int) ($statuses['overdue'] ?? 0), 'completed' => (int) ($statuses['completed'] ?? 0),
                ],
            ],
            'rows' => $rows,
            'profit' => $this->profit($sum('fines_issued'), $sum('fines_paid'), (float) $charged->interest, (float) $received->interest, (float) $charged->penalty, (float) $received->penalty, $incomes, $expenses),
            'checks' => ['hisa_difference' => round($hisaFund - $sum('shares_amount'), 2)],
        ];
    }

    /**
     * Gross profit (fines, loan interest, loan penalties and other income) as current (received), expected (still to come) and total,
     * the expenses subtracted from it, and the net profit. Saved income counts in full, as received. Approved and paid expenses are
     * spent (current) and draft ones are expected.
     *
     * @param  array<int, array{name: string, amount: float}>  $incomes
     * @param  array<int, array{name: string, spent: float, planned: float}>  $expenses
     * @return array<string, mixed>
     */
    private function profit(float $finesIssued, float $finesPaid, float $interest, float $interestReceived, float $penalty, float $penaltyReceived, array $incomes, array $expenses): array
    {
        $line = fn (float $total, float $current): array => ['current' => round($current, 2), 'expected' => round(max($total - $current, 0), 2), 'total' => round(max($total, $current), 2)];
        $add = fn (array ...$lines): array => [
            'current' => round(array_sum(array_column($lines, 'current')), 2),
            'expected' => round(array_sum(array_column($lines, 'expected')), 2),
            'total' => round(array_sum(array_column($lines, 'total')), 2),
        ];

        $incomeTotal = round(array_sum(array_column($incomes, 'amount')), 2);
        $fines = $line($finesIssued, $finesPaid);
        $loanInterest = $line($interest, $interestReceived);
        $loanPenalties = $line($penalty, $penaltyReceived);
        $otherIncome = $line($incomeTotal, $incomeTotal);
        $gross = $add($fines, $loanInterest, $loanPenalties, $otherIncome);

        $spent = round(array_sum(array_column($expenses, 'spent')), 2);
        $planned = round(array_sum(array_column($expenses, 'planned')), 2);
        $expenseLine = ['current' => $spent, 'expected' => $planned, 'total' => round($spent + $planned, 2)];

        return [
            'fines' => $fines,
            'loan_interest' => $loanInterest,
            'loan_penalties' => $loanPenalties,
            'loans' => $add($loanInterest, $loanPenalties),
            'income' => $otherIncome,
            'income_categories' => $incomes,
            'gross' => $gross,
            'expenses' => $expenseLine,
            'expense_categories' => $expenses,
            'net' => [
                'current' => round($gross['current'] - $expenseLine['current'], 2),
                'expected' => round($gross['expected'] - $expenseLine['expected'], 2),
                'total' => round($gross['total'] - $expenseLine['total'], 2),
            ],
        ];
    }
}
