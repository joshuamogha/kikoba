<?php

namespace App\Console\Commands;

use App\Models\LoanManagement\LoanInstallment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loans:mark-overdue')]
#[Description('Mark unpaid installments past their due date as overdue and apply each group\'s late payment penalty')]
class MarkOverdueLoans extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $marked = 0;

        LoanInstallment::query()
            ->with('loan.group')
            ->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', today())
            ->whereHas('loan', fn ($loan) => $loan->whereIn('status', ['active', 'overdue']))
            ->each(function (LoanInstallment $installment) use (&$marked): void {
                $loan = $installment->loan;
                $penalty = (float) $loan->group->ensureGroupSetting()->late_payment_penalty_value;
                $applied = (float) $installment->penalty_due === 0.0 ? $penalty : 0.0;

                $installment->update([
                    'status' => 'overdue',
                    'penalty_due' => (float) $installment->penalty_due + $applied,
                    'total_due' => (float) $installment->total_due + $applied,
                ]);

                $loan->update([
                    'status' => 'overdue',
                    'penalty_amount' => (float) $loan->penalty_amount + $applied,
                    'total_payable' => (float) $loan->total_payable + $applied,
                    'outstanding_balance' => (float) $loan->outstanding_balance + $applied,
                ]);

                $marked++;
            });

        $this->info("Marked {$marked} installments as overdue.");

        return self::SUCCESS;
    }
}
