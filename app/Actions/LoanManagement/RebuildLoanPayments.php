<?php

namespace App\Actions\LoanManagement;

use App\Models\LoanManagement\Loan;

class RebuildLoanPayments
{
    public function __construct(private AllocateLoanRepayment $allocate) {}

    /**
     * Work out a loan's installments, balance and status again from its approved repayments. Used after a repayment is changed or
     * deleted: everything that was allocated is cleared, then the approved repayments are applied again in date order, oldest
     * installment first. Penalties already added to installments stay.
     */
    public function handle(Loan $loan): void
    {
        $loan->loanInstallments()->get()->each(fn ($installment) => $installment->update([
            'principal_paid' => 0,
            'interest_paid' => 0,
            'penalty_paid' => 0,
            'total_paid' => 0,
            'status' => (float) $installment->penalty_due > 0 ? 'overdue' : 'pending',
            'paid_at' => null,
        ]));

        $loan->update([
            'amount_paid' => 0,
            'outstanding_balance' => $loan->total_payable,
            'status' => $loan->loanInstallments()->where('status', 'overdue')->exists() ? 'overdue' : 'active',
        ]);

        $loan->loanRepayments()->where('status', 'approved')->orderBy('payment_date')->orderBy('id')->get()
            ->each(fn ($repayment) => $this->allocate->handle($repayment->setRelation('loan', $loan->refresh())));
    }
}
