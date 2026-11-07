<?php

namespace App\Actions\LoanManagement;

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\UserManagement\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DisburseLoan
{
    public function __construct(private BuildLoanSchedule $buildSchedule, private ResolveFinancialCycle $resolveCycle) {}

    /**
     * Turn an approved application into a loan with its installment schedule.
     */
    public function handle(LoanApplication $application, User $user, Carbon $disbursementDate): Loan
    {
        return DB::transaction(function () use ($application, $user, $disbursementDate): Loan {
            $product = $application->loanProduct;
            $interval = $product->repaymentFrequency->interval_days;
            $principal = (float) $application->approved_amount;

            $schedule = $product->repayment_mode === 'lump_sum'
                ? $this->buildSchedule->lumpSum(
                    $principal,
                    (float) $application->interest_rate,
                    $product->interest_period,
                    $application->duration_months,
                    $disbursementDate->copy()->addMonthsNoOverflow($application->duration_months)->addDays($product->grace_period_days),
                )
                : $this->buildSchedule->handle(
                    $principal,
                    (float) $application->interest_rate,
                    $product->interest_period,
                    $application->loanInterestMethod->code,
                    $application->duration_months,
                    $interval,
                    $disbursementDate->copy()->addDays($product->grace_period_days + $interval),
                );

            $interest = round(collect($schedule)->sum('interest'), 2);

            $loan = $application->group->loans()->create([
                'financial_cycle_id' => $this->resolveCycle->handle($application->group, $disbursementDate, $user->id)->id,
                'loan_application_id' => $application->id,
                'group_member_id' => $application->group_member_id,
                'loan_product_id' => $product->id,
                'loan_number' => 'PENDING-'.uniqid(),
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'penalty_amount' => 0,
                'total_payable' => round($principal + $interest, 2),
                'amount_paid' => 0,
                'outstanding_balance' => round($principal + $interest, 2),
                'disbursement_date' => $disbursementDate->toDateString(),
                'first_payment_date' => $schedule[0]['due_date']->toDateString(),
                'maturity_date' => end($schedule)['due_date']->toDateString(),
                'interest_rate' => $application->interest_rate,
                'loan_interest_method_id' => $application->loan_interest_method_id,
                'status' => 'active',
            ]);

            $loan->update(['loan_number' => sprintf('LN-%d-%06d', $disbursementDate->year, $loan->id)]);

            $loan->loanInstallments()->createMany(collect($schedule)->map(fn (array $row) => [
                'installment_number' => $row['number'],
                'due_date' => $row['due_date']->toDateString(),
                'principal_due' => $row['principal'],
                'interest_due' => $row['interest'],
                'penalty_due' => 0,
                'total_due' => $row['total'],
                'status' => 'pending',
            ])->all());

            return $loan;
        });
    }
}
