<?php

namespace App\Actions\LoanManagement;

use Illuminate\Support\Carbon;

class BuildLoanSchedule
{
    /**
     * Split a loan into installments. Flat rate charges the same interest on every installment; reducing balance
     * charges interest on the outstanding principal with equal total payments. The last installment absorbs rounding.
     *
     * @param  string  $interestPeriod  per_loan, per_month or per_year: what the rate is quoted over
     * @param  string  $methodCode  FLAT or REDUCING
     * @return array<int, array{number: int, due_date: Carbon, principal: float, interest: float, total: float}>
     */
    public function handle(float $principal, float $ratePercent, string $interestPeriod, string $methodCode, int $months, int $intervalDays, Carbon $firstDueDate): array
    {
        $count = max(1, (int) round($months * 30 / max(1, $intervalDays)));
        $rate = $this->ratePerInstallment($ratePercent / 100, $interestPeriod, $count, $intervalDays);

        $rows = [];
        $balance = $principal;
        $payment = $this->reducingPayment($principal, $rate, $count);
        $principalPaid = 0.0;
        $interestPaid = 0.0;

        for ($number = 1; $number <= $count; $number++) {
            $last = $number === $count;

            if ($methodCode === 'REDUCING') {
                $interest = round($balance * $rate, 2);
                $principalPart = $last ? round($principal - $principalPaid, 2) : round($payment - $interest, 2);
            } else {
                $interest = $last ? round($principal * $rate * $count - $interestPaid, 2) : round($principal * $rate, 2);
                $principalPart = $last ? round($principal - $principalPaid, 2) : round($principal / $count, 2);
            }

            $rows[] = [
                'number' => $number,
                'due_date' => $firstDueDate->copy()->addDays(($number - 1) * $intervalDays),
                'principal' => $principalPart,
                'interest' => $interest,
                'total' => round($principalPart + $interest, 2),
            ];

            $balance -= $principalPart;
            $principalPaid += $principalPart;
            $interestPaid += $interest;
        }

        return $rows;
    }

    /**
     * A single installment: the whole principal plus the interest for the term, due on one date. Flat and reducing balance give
     * the same result for one payment.
     *
     * @param  string  $interestPeriod  per_loan, per_month or per_year: what the rate is quoted over
     * @return array<int, array{number: int, due_date: Carbon, principal: float, interest: float, total: float}>
     */
    public function lumpSum(float $principal, float $ratePercent, string $interestPeriod, int $months, Carbon $dueDate): array
    {
        $interest = round($principal * $ratePercent / 100 * match ($interestPeriod) {
            'per_month' => $months,
            'per_year' => $months / 12,
            default => 1,
        }, 2);

        return [[
            'number' => 1,
            'due_date' => $dueDate,
            'principal' => round($principal, 2),
            'interest' => $interest,
            'total' => round($principal + $interest, 2),
        ]];
    }

    private function ratePerInstallment(float $rate, string $interestPeriod, int $count, int $intervalDays): float
    {
        return match ($interestPeriod) {
            'per_month' => $rate * $intervalDays / 30,
            'per_year' => $rate * $intervalDays / 365,
            default => $rate / $count,
        };
    }

    private function reducingPayment(float $principal, float $rate, int $count): float
    {
        return $rate > 0
            ? $principal * $rate / (1 - (1 + $rate) ** -$count)
            : $principal / $count;
    }
}
