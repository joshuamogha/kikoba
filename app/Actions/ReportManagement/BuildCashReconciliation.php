<?php

namespace App\Actions\ReportManagement;

use App\Models\LoanManagement\LoanInstallment;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\ContributionItem;
use App\Models\PaymentManagement\FinePayment;
use App\Models\PaymentManagement\GroupFund;
use App\Models\ShareManagement\ShareOutPayment;
use App\Models\UserManagement\Group;
use Illuminate\Support\Carbon;

class BuildCashReconciliation
{
    /**
     * Every approved or paid movement of money in a period, to compare the expected balance with the mobile money account.
     * Deposits are all contributions (savings, shares and every other fund) plus the penalties paid (member fines and loan late
     * penalties); social funds, insurance, membership fees, other contributions and penalty paid are shown as parts of deposits.
     * Loan repayments (principal and interest) and other income also come in; loan disbursements and paid expenses are the withdrawals, and dividends (share-out payments) go out as their own line. Penalties charged (fines issued and late
     * penalties on loan installments) are shown for reference because they are not cash until paid.
     *
     * Loans have no payment method, so loan disbursements are left out when a payment method is chosen; the group's opening balance
     * is a single figure, so it is left out then too. With an opening balance, only movements from its date on are counted, and a From
     * date after it carries the movements in between into the balance brought forward.
     *
     * @return array{figures: array<string, float>, money_in: float, opening: array{amount: float, as_of: string|null, brought_forward: float, applies: bool}, balance: float}
     */
    public function handle(Group $group, ?string $from, ?string $to, ?int $paymentMethodId): array
    {
        $setting = $group->ensureGroupSetting();
        $openingDate = $paymentMethodId === null ? $setting->opening_balance_date?->toDateString() : null;
        $opening = $openingDate === null ? 0.0 : (float) $setting->opening_balance;
        $broughtForward = $opening;

        if ($openingDate !== null) {
            if ($from !== null && $from > $openingDate) {
                $before = $this->movements($group, $openingDate, Carbon::parse($from)->subDay()->toDateString(), null);
                $broughtForward = round($opening + $before['balance'], 2);
            } else {
                $from = $openingDate;
            }
        }

        $report = $this->movements($group, $from, $to, $paymentMethodId);

        return [
            ...$report,
            'opening' => ['amount' => $opening, 'as_of' => $openingDate, 'brought_forward' => $broughtForward, 'applies' => $paymentMethodId === null],
            'balance' => round($broughtForward + $report['balance'], 2),
        ];
    }

    /**
     * The movements of money between two dates, without any opening balance. Its balance is money in minus withdrawals and dividends.
     *
     * @return array{figures: array<string, float>, money_in: float, balance: float}
     */
    private function movements(Group $group, ?string $from, ?string $to, ?int $paymentMethodId): array
    {
        $between = fn ($query, string $column) => $query
            ->when($from !== null, fn ($inner) => $inner->whereDate($column, '>=', $from))
            ->when($to !== null, fn ($inner) => $inner->whereDate($column, '<=', $to));
        $byMethod = fn ($query) => $query->when($paymentMethodId !== null, fn ($inner) => $inner->where('payment_method_id', $paymentMethodId));

        $funds = GroupFund::query()->where('group_id', $group->id)->with('fundType:id,code')->get()->keyBy('id');
        $items = ContributionItem::query()
            ->join('contributions', 'contributions.id', '=', 'contribution_items.contribution_id')
            ->where('contributions.group_id', $group->id)
            ->where('contributions.status', 'approved')
            ->when($paymentMethodId !== null, fn ($query) => $query->where('contributions.payment_method_id', $paymentMethodId))
            ->when($from !== null, fn ($query) => $query->whereDate('contributions.contribution_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('contributions.contribution_date', '<=', $to))
            ->groupBy('contribution_items.group_fund_id')
            ->selectRaw('contribution_items.group_fund_id as fund_id, sum(contribution_items.amount) as total')
            ->get();

        $contributions = ['all' => 0.0, 'social' => 0.0, 'insurance' => 0.0, 'membership' => 0.0, 'other' => 0.0];

        foreach ($items as $item) {
            $fund = $funds[$item->fund_id];
            $amount = (float) $item->total;
            $contributions['all'] += $amount;

            $kind = $this->fundKind($fund);

            if ($kind !== null) {
                $contributions[$kind] += $amount;
            }
        }

        // Share purchases recorded before purchases created contributions have no contribution, so they are added here. Linked ones
        // are already counted above. A purchase has no payment method of its own, so these are left out when one is chosen.
        if ($paymentMethodId === null) {
            $contributions['all'] += (float) $between($group->memberShareTransactions()
                ->where('transaction_type', 'purchase')->where('status', 'approved')->whereNull('contribution_id'), 'transaction_date')->sum('total_amount');
        }

        $repayments = $between($byMethod(LoanRepayment::query()
            ->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))
            ->where('status', 'approved')), 'payment_date');
        $loanPaid = $repayments->selectRaw('coalesce(sum(principal_amount), 0) as principal, coalesce(sum(interest_amount), 0) as interest, coalesce(sum(penalty_amount), 0) as penalty')->first();

        $finesPaid = (float) $between($byMethod(FinePayment::query()
            ->whereHas('memberFine', fn ($fine) => $fine->where('group_id', $group->id))
            ->where('status', 'approved')), 'payment_date')->sum('amount');

        $income = (float) $between($byMethod($group->groupIncomes()->where('status', 'approved')), 'income_date')->sum('amount');

        $disbursed = $paymentMethodId === null ? (float) $between($group->loans(), 'disbursement_date')->sum('principal_amount') : 0.0;
        $expenses = (float) $between($byMethod($group->groupExpenses()->where('status', 'paid')), 'paid_at')->sum('amount');

        $finesIssued = (float) $between($group->memberFines()->where('status', '!=', 'waived'), 'fine_date')->sum('amount');
        $loanPenalties = (float) $between(LoanInstallment::query()->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id)), 'due_date')->sum('penalty_due');

        $dividends = (float) $between($byMethod(ShareOutPayment::query()
            ->whereHas('memberShareOut.shareOut', fn ($shareOut) => $shareOut->where('group_id', $group->id))
            ->where('status', ShareOutPayment::PAID)), 'payment_date')->sum('amount');

        $repaid = (float) $loanPaid->principal + (float) $loanPaid->interest;
        $penaltiesPaid = (float) $loanPaid->penalty + $finesPaid;
        $withdrawals = $disbursed + $expenses;
        $deposits = $contributions['all'] + $penaltiesPaid;
        $moneyIn = $deposits + $repaid + $income;

        $figures = [
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'penalties' => $finesIssued + $loanPenalties,
            'penalties_paid' => $penaltiesPaid,
            'social_funds' => $contributions['social'],
            'repayments' => $repaid,
            'insurance' => $contributions['insurance'],
            'other_contribution' => $contributions['other'],
            'membership_fee' => $contributions['membership'],
            'dividends' => $dividends,
            'other' => $income,
        ];

        return [
            'figures' => array_map(fn (float $amount) => round($amount, 2), $figures),
            'money_in' => round($moneyIn, 2),
            'balance' => round($moneyIn - $withdrawals - $dividends, 2),
        ];
    }

    /**
     * Which kind of contribution a fund collects, when it is one the reconciliation lists on its own line: the Social Fund,
     * insurance, membership fees, or (null for savings and shares) any other fund as "other".
     *
     * @return 'social'|'insurance'|'membership'|'other'|null
     */
    private function fundKind(GroupFund $fund): ?string
    {
        $code = strtoupper((string) $fund->code);
        $typeCode = strtoupper((string) $fund->fundType?->code);
        $name = mb_strtolower($fund->name);

        return match (true) {
            $code === 'SOC' || $typeCode === 'SOC' => 'social',
            $code === 'INS' || $typeCode === 'INS' || str_contains($name, 'insurance') || str_contains($name, 'bima') => 'insurance',
            $code === 'MEM' || $typeCode === 'MEM' || str_contains($name, 'membership') || str_contains($name, 'uanachama') => 'membership',
            in_array($code, ['SAV', 'SHR'], true) || in_array($typeCode, ['SAV', 'SHR'], true) => null,
            default => 'other',
        };
    }
}
