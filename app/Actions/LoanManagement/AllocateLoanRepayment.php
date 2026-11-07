<?php

namespace App\Actions\LoanManagement;

use App\Models\LoanManagement\LoanRepayment;

class AllocateLoanRepayment
{
    /**
     * Apply an approved repayment to the loan's unpaid installments, oldest first, paying penalty, then interest, then principal.
     * Records the split on the repayment and updates the loan balance and status.
     */
    public function handle(LoanRepayment $repayment): void
    {
        $loan = $repayment->loan;
        $remaining = (float) $repayment->amount;
        $split = ['penalty' => 0.0, 'interest' => 0.0, 'principal' => 0.0];

        $installments = $loan->loanInstallments()->where('status', '!=', 'paid')->orderBy('installment_number')->get();

        foreach ($installments as $installment) {
            if ($remaining <= 0) {
                break;
            }

            $paid = ['penalty' => 0.0, 'interest' => 0.0, 'principal' => 0.0];

            foreach (['penalty', 'interest', 'principal'] as $part) {
                $owed = round((float) $installment->{"{$part}_due"} - (float) $installment->{"{$part}_paid"}, 2);
                $paid[$part] = min($owed, $remaining);
                $remaining = round($remaining - $paid[$part], 2);
                $split[$part] += $paid[$part];
            }

            $totalPaid = round((float) $installment->total_paid + array_sum($paid), 2);
            $settled = $totalPaid >= (float) $installment->total_due;

            $installment->update([
                'penalty_paid' => (float) $installment->penalty_paid + $paid['penalty'],
                'interest_paid' => (float) $installment->interest_paid + $paid['interest'],
                'principal_paid' => (float) $installment->principal_paid + $paid['principal'],
                'total_paid' => $totalPaid,
                'status' => $settled ? 'paid' : ($installment->status === 'overdue' ? 'overdue' : 'partial'),
                'paid_at' => $settled ? now() : null,
            ]);
        }

        $repayment->update([
            'penalty_amount' => $split['penalty'],
            'interest_amount' => $split['interest'],
            'principal_amount' => $split['principal'],
        ]);

        $amountPaid = round((float) $loan->amount_paid + (float) $repayment->amount, 2);
        $outstanding = max(0, round((float) $loan->total_payable - $amountPaid, 2));

        $loan->update([
            'amount_paid' => $amountPaid,
            'outstanding_balance' => $outstanding,
            'status' => $outstanding <= 0 ? 'completed' : ($loan->loanInstallments()->where('status', 'overdue')->exists() ? 'overdue' : 'active'),
        ]);
    }
}
