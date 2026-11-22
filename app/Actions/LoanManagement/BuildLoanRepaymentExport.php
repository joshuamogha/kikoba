<?php

namespace App\Actions\LoanManagement;

use App\Models\LoanManagement\LoanRepayment;

class BuildLoanRepaymentExport
{
    /**
     * The columns a repayments export can contain: label, whether it is an amount (and so totalled), and how to read it from a
     * repayment. Repayments must be loaded with loan, groupMember and paymentMethod.
     *
     * @return array<string, array{label: string, amount: bool, value: callable(LoanRepayment): (string|float|null)}>
     */
    public static function columns(): array
    {
        return [
            'repayment_number' => ['label' => 'Repayment number', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->repayment_number],
            'loan_number' => ['label' => 'Loan number', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->loan->loan_number],
            'member' => ['label' => 'Member', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->groupMember->name],
            'member_number' => ['label' => 'Member number', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->groupMember->member_number],
            'payment_date' => ['label' => 'Date paid', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->payment_date->toDateString()],
            'payment_method' => ['label' => 'Payment method', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->paymentMethod?->name],
            'external_reference' => ['label' => 'Reference', 'amount' => false, 'value' => fn (LoanRepayment $row) => $row->external_reference],
            'amount' => ['label' => 'Amount', 'amount' => true, 'value' => fn (LoanRepayment $row) => (float) $row->amount],
            'principal_amount' => ['label' => 'Principal', 'amount' => true, 'value' => fn (LoanRepayment $row) => (float) $row->principal_amount],
            'interest_amount' => ['label' => 'Interest', 'amount' => true, 'value' => fn (LoanRepayment $row) => (float) $row->interest_amount],
            'penalty_amount' => ['label' => 'Penalty', 'amount' => true, 'value' => fn (LoanRepayment $row) => (float) $row->penalty_amount],
            'status' => ['label' => 'Status', 'amount' => false, 'value' => fn (LoanRepayment $row) => ucfirst($row->status)],
        ];
    }
}
