<?php

namespace App\Actions\ShareManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use App\Models\UserManagement\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculateShareOut
{
    /**
     * Fund whose income and expenses are shared out with the interest and fines.
     */
    public const GENERAL_FUND = 'GEN';

    public const SAVINGS_FUND = 'SAV';

    /**
     * Calculate the share out of a closed cycle, or recalculate it while it is still a draft.
     *
     * @throws ValidationException
     */
    public function handle(FinancialCycle $cycle, User $calculator, ?string $calculationDate = null): ShareOut
    {
        $existing = $cycle->shareOut;

        if ($cycle->status !== 'closed') {
            throw ValidationException::withMessages(['financial_cycle_id' => 'Only a closed cycle can be shared out.']);
        }

        if ($existing !== null && $existing->status !== ShareOut::DRAFT) {
            throw ValidationException::withMessages(['financial_cycle_id' => 'This share out is already approved and can no longer be recalculated.']);
        }

        return DB::transaction(function () use ($cycle, $calculator, $calculationDate, $existing): ShareOut {
            $income = $this->groupIncome($cycle);
            $members = $this->memberFigures($cycle, $income['profit']);

            $attributes = [
                'calculation_date' => $calculationDate ?? now()->toDateString(),
                'total_savings' => array_sum(array_column($members, 'total_savings')),
                'total_share_value' => array_sum(array_column($members, 'share_value')),
                'total_interest_income' => $income['interest'],
                'total_other_income' => $income['other'],
                'total_fine_income' => $income['fines'],
                'total_expenses' => $income['expenses'],
                'distributable_profit' => $income['profit'],
                'total_distributable' => array_sum(array_column($members, 'net_payable')),
                'calculated_by' => $calculator->id,
            ];

            if ($existing !== null) {
                $existing->memberShareOuts()->delete();
                $existing->update($attributes);
                $shareOut = $existing;
            } else {
                $shareOut = ShareOut::query()->create($attributes + [
                    'group_id' => $cycle->group_id,
                    'financial_cycle_id' => $cycle->id,
                    'share_out_number' => $this->numberFor($cycle),
                    'status' => ShareOut::DRAFT,
                ]);
            }

            foreach ($members as $memberId => $figures) {
                MemberShareOut::query()->create($figures + [
                    'share_out_id' => $shareOut->id,
                    'group_member_id' => $memberId,
                    'status' => MemberShareOut::PENDING,
                ]);
            }

            return $shareOut;
        });
    }

    /**
     * Income earned and money spent in the cycle, and the profit left to distribute.
     *
     * @return array{interest: float, fines: float, other: float, expenses: float, profit: float}
     */
    private function groupIncome(FinancialCycle $cycle): array
    {
        $period = [$cycle->start_date, $cycle->end_date];
        $generalFundId = $this->fundId($cycle, self::GENERAL_FUND);

        $interest = (float) DB::table('loan_repayments')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->where('loans.group_id', $cycle->group_id)
            ->whereBetween('loan_repayments.payment_date', $period)
            ->sum('loan_repayments.interest_amount');

        $fines = (float) DB::table('fine_payments')
            ->join('member_fines', 'member_fines.id', '=', 'fine_payments.member_fine_id')
            ->where('member_fines.group_id', $cycle->group_id)
            ->whereBetween('fine_payments.payment_date', $period)
            ->sum('fine_payments.amount');

        $other = $generalFundId === null ? 0.0 : (float) DB::table('group_incomes')
            ->where('group_id', $cycle->group_id)
            ->where('financial_cycle_id', $cycle->id)
            ->where('group_fund_id', $generalFundId)
            ->sum('amount');

        $expenses = $generalFundId === null ? 0.0 : (float) DB::table('group_expenses')
            ->where('group_id', $cycle->group_id)
            ->where('financial_cycle_id', $cycle->id)
            ->where('group_fund_id', $generalFundId)
            ->sum('amount');

        return [
            'interest' => $interest,
            'fines' => $fines,
            'other' => $other,
            'expenses' => $expenses,
            'profit' => max(0.0, $interest + $fines + $other - $expenses),
        ];
    }

    /**
     * What every member is owed, keyed by group member id.
     *
     * @return array<int, array<string, float|int>>
     */
    private function memberFigures(FinancialCycle $cycle, float $profit): array
    {
        $savingsFundId = $this->fundId($cycle, self::SAVINGS_FUND);

        $savings = $savingsFundId === null ? collect() : DB::table('contribution_items')
            ->join('contributions', 'contributions.id', '=', 'contribution_items.contribution_id')
            ->where('contributions.financial_cycle_id', $cycle->id)
            ->where('contributions.status', 'approved')
            ->where('contribution_items.group_fund_id', $savingsFundId)
            ->groupBy('contributions.group_member_id')
            ->selectRaw('contributions.group_member_id as member_id, SUM(contribution_items.amount) as total')
            ->pluck('total', 'member_id');

        $shares = DB::table('member_share_transactions')
            ->where('financial_cycle_id', $cycle->id)
            ->where('transaction_type', 'purchase')
            ->where('status', 'approved')
            ->groupBy('group_member_id')
            ->selectRaw('group_member_id as member_id, SUM(number_of_shares) as shares, SUM(total_amount) as value')
            ->get()
            ->keyBy('member_id');

        $loans = DB::table('loans')->where('financial_cycle_id', $cycle->id)
            ->groupBy('group_member_id')->selectRaw('group_member_id as member_id, SUM(outstanding_balance) as total')->pluck('total', 'member_id');

        $fines = DB::table('member_fines')->where('financial_cycle_id', $cycle->id)
            ->groupBy('group_member_id')->selectRaw('group_member_id as member_id, SUM(balance) as total')->pluck('total', 'member_id');

        $memberIds = DB::table('group_members')
            ->where('group_id', $cycle->group_id)
            ->where('membership_status', 'active')
            ->pluck('id')
            ->merge($savings->keys())
            ->merge($shares->keys())
            ->unique()
            ->sort()
            ->values();

        $totalShares = (float) $shares->sum('shares');
        $totalSavings = (float) $savings->sum();

        $rows = [];

        foreach ($memberIds as $memberId) {
            $memberShares = (float) ($shares[$memberId]->shares ?? 0);
            $memberSavings = (float) ($savings[$memberId] ?? 0);

            // Profit follows shares held; a group that bought no shares splits it by savings instead.
            $profitShare = match (true) {
                $totalShares > 0 => floor($profit * $memberShares / $totalShares),
                $totalSavings > 0 => floor($profit * $memberSavings / $totalSavings),
                default => 0.0,
            };

            $shareValue = (float) ($shares[$memberId]->value ?? 0);
            $loan = (float) ($loans[$memberId] ?? 0);
            $fine = (float) ($fines[$memberId] ?? 0);

            $rows[$memberId] = [
                'total_savings' => $memberSavings,
                'number_of_shares' => (int) $memberShares,
                'share_value' => $shareValue,
                'profit_amount' => $profitShare,
                'other_entitlements' => 0,
                'outstanding_loan' => $loan,
                'outstanding_fines' => $fine,
                'other_deductions' => 0,
                'net_payable' => max(0.0, $memberSavings + $shareValue + $profitShare - $loan - $fine),
                'amount_paid' => 0,
            ];
        }

        return $rows;
    }

    private function fundId(FinancialCycle $cycle, string $code): ?int
    {
        $id = DB::table('group_funds')->where('group_id', $cycle->group_id)->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function numberFor(FinancialCycle $cycle): string
    {
        return sprintf('G%d-SO-%s-%02d', $cycle->group_id, Carbon::parse($cycle->end_date)->year, $cycle->cycle_number);
    }
}
