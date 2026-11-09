<?php

namespace App\Actions\ReportManagement;

use App\Models\LoanManagement\LoanInstallment;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\ContributionItem;
use App\Models\PaymentManagement\FinePayment;
use App\Models\PaymentManagement\GroupFund;
use App\Models\UserManagement\Group;
use Illuminate\Support\Carbon;

class BuildCashReconciliation
{
    /**
     * Every approved or paid movement of money in a period, to compare the expected balance with the mobile money account: deposits,
     * social funds, loan repayments, penalties paid and income come in; loan disbursements and paid expenses go out. Penalties
     * charged (fines issued and late penalties on loan installments) are shown separately because they are not cash yet.
     *
     * Loans have no payment method, so loan disbursements are left out when a payment method is chosen; the group's opening balance
     * is a single figure, so it is left out then too. With an opening balance, only movements from its date on are counted, and a From
     * date after it carries the movements in between into the balance brought forward.
     *
     * @return array{sections: array<int, array{key: string, title: string, rows: array<int, array{label: string, amount: float}>, total: float}>, summary: array<string, float>, opening: array{amount: float, as_of: string|null, brought_forward: float, applies: bool}, balance: float}
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
     * The movements of money between two dates, without any opening balance. Its balance is money in minus withdrawals.
     *
     * @return array{sections: array<int, array{key: string, title: string, rows: array<int, array{label: string, amount: float}>, total: float}>, summary: array<string, float>, balance: float}
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

        $deposits = [];
        $social = [];

        foreach ($items as $item) {
            $fund = $funds[$item->fund_id];
            $row = ['label' => $fund->name, 'amount' => round((float) $item->total, 2)];

            if ($fund->code === 'SOC' || $fund->fundType?->code === 'SOC') {
                $social[] = $row;
            } else {
                $deposits[] = $row;
            }
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

        $sections = [
            $this->section('deposits', 'Deposits (savings, shares and other funds)', $deposits),
            $this->section('social', 'Social funds', $social),
            $this->section('repayments', 'Loan repayments (without penalties)', [
                ['label' => 'Principal', 'amount' => (float) $loanPaid->principal],
                ['label' => 'Interest', 'amount' => (float) $loanPaid->interest],
            ]),
            $this->section('penalties_paid', 'Penalties paid', [
                ['label' => 'Loan late penalties', 'amount' => (float) $loanPaid->penalty],
                ['label' => 'Member fines', 'amount' => $finesPaid],
            ]),
            $this->section('income', 'Other income', [['label' => 'Group income', 'amount' => $income]]),
            $this->section('withdrawals', 'Withdrawals', [
                ['label' => 'Loan disbursements', 'amount' => $disbursed],
                ['label' => 'Expenses paid', 'amount' => $expenses],
            ]),
            $this->section('penalties_charged', 'Penalties charged (not cash until paid)', [
                ['label' => 'Loan late penalties', 'amount' => $loanPenalties],
                ['label' => 'Member fines issued', 'amount' => $finesIssued],
            ]),
        ];

        $totals = collect($sections)->pluck('total', 'key');
        $moneyIn = $totals['deposits'] + $totals['social'] + $totals['repayments'] + $totals['penalties_paid'] + $totals['income'];

        return [
            'sections' => $sections,
            'summary' => [
                'deposits' => $totals['deposits'],
                'withdrawals' => $totals['withdrawals'],
                'penalties' => $totals['penalties_charged'],
                'penalties_paid' => $totals['penalties_paid'],
                'social_funds' => $totals['social'],
                'repayments' => $totals['repayments'],
                'income' => $totals['income'],
                'money_in' => round($moneyIn, 2),
            ],
            'balance' => round($moneyIn - $totals['withdrawals'], 2),
        ];
    }

    /**
     * @param  array<int, array{label: string, amount: float}>  $rows
     * @return array{key: string, title: string, rows: array<int, array{label: string, amount: float}>, total: float}
     */
    private function section(string $key, string $title, array $rows): array
    {
        $rows = array_map(fn (array $row) => ['label' => $row['label'], 'amount' => round($row['amount'], 2)], $rows);

        return ['key' => $key, 'title' => $title, 'rows' => $rows, 'total' => round(array_sum(array_column($rows, 'amount')), 2)];
    }
}
