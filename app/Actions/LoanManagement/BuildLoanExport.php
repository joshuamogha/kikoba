<?php

namespace App\Actions\LoanManagement;

use App\Models\LoanManagement\Loan;

class BuildLoanExport
{
    /**
     * The columns a loan export can contain: label, whether it is an amount (and so totalled), and how to read it from a loan.
     * Loans must be loaded with groupMember and loanProduct.
     *
     * @return array<string, array{label: string, amount: bool, value: callable(Loan): (string|float|null)}>
     */
    public static function columns(): array
    {
        return [
            'loan_number' => ['label' => 'Loan number', 'amount' => false, 'value' => fn (Loan $loan) => $loan->loan_number],
            'member' => ['label' => 'Member', 'amount' => false, 'value' => fn (Loan $loan) => $loan->groupMember->name],
            'member_number' => ['label' => 'Member number', 'amount' => false, 'value' => fn (Loan $loan) => $loan->groupMember->member_number],
            'product' => ['label' => 'Loan product', 'amount' => false, 'value' => fn (Loan $loan) => $loan->loanProduct->name],
            'principal_amount' => ['label' => 'Principal', 'amount' => true, 'value' => fn (Loan $loan) => (float) $loan->principal_amount],
            'interest_amount' => ['label' => 'Interest', 'amount' => true, 'value' => fn (Loan $loan) => (float) $loan->interest_amount],
            'penalty_amount' => ['label' => 'Penalty', 'amount' => true, 'value' => fn (Loan $loan) => (float) $loan->penalty_amount],
            'total_payable' => ['label' => 'Total payable', 'amount' => true, 'value' => fn (Loan $loan) => (float) $loan->total_payable],
            'amount_paid' => ['label' => 'Paid', 'amount' => true, 'value' => fn (Loan $loan) => (float) $loan->amount_paid],
            'outstanding_balance' => ['label' => 'Outstanding', 'amount' => true, 'value' => fn (Loan $loan) => (float) $loan->outstanding_balance],
            'interest_rate' => ['label' => 'Interest rate (%)', 'amount' => false, 'value' => fn (Loan $loan) => (float) $loan->interest_rate],
            'disbursement_date' => ['label' => 'Disbursed', 'amount' => false, 'value' => fn (Loan $loan) => $loan->disbursement_date->toDateString()],
            'first_payment_date' => ['label' => 'First payment', 'amount' => false, 'value' => fn (Loan $loan) => $loan->first_payment_date?->toDateString()],
            'maturity_date' => ['label' => 'Maturity', 'amount' => false, 'value' => fn (Loan $loan) => $loan->maturity_date->toDateString()],
            'status' => ['label' => 'Status', 'amount' => false, 'value' => fn (Loan $loan) => ucfirst($loan->status)],
        ];
    }
}
