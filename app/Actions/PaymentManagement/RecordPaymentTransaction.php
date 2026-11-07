<?php

namespace App\Actions\PaymentManagement;

use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\FinePayment;
use App\Models\PaymentManagement\GroupExpense;
use App\Models\PaymentManagement\GroupIncome;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\UserManagement\User;

class RecordPaymentTransaction
{
    /**
     * Log a completed payment transaction for an approved contribution, income or loan repayment, or a paid expense.
     */
    public function handle(Contribution|GroupIncome|GroupExpense|LoanRepayment|FinePayment $source, User $user): PaymentTransaction
    {
        [$type, $amount, $memberId, $group] = match (true) {
            $source instanceof Contribution => ['contribution', $source->total_amount, $source->group_member_id, $source->group],
            $source instanceof LoanRepayment => ['loan_repayment', $source->amount, $source->group_member_id, $source->loan->group],
            $source instanceof FinePayment => ['fine_payment', $source->amount, $source->memberFine->group_member_id, $source->memberFine->group],
            $source instanceof GroupIncome => ['income', $source->amount, null, $source->group],
            default => ['expense', $source->amount, null, $source->group],
        };

        $transaction = PaymentTransaction::query()->create([
            'group_id' => $group->id,
            'group_member_id' => $memberId,
            'transaction_number' => 'PENDING-'.uniqid(),
            'transaction_type' => $type,
            'payment_method_id' => $source->payment_method_id,
            'amount' => $amount,
            'currency' => $group->currency,
            'provider_transaction_id' => $source->external_reference,
            'reference_type' => $source::class,
            'reference_id' => $source->id,
            'status' => 'completed',
            'initiated_at' => now(),
            'completed_at' => now(),
            'created_by' => $user->id,
        ]);

        $transaction->update(['transaction_number' => sprintf('TXN-%d-%07d', now()->year, $transaction->id)]);

        return $transaction;
    }
}
