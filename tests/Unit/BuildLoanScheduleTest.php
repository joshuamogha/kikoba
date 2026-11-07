<?php

use App\Actions\LoanManagement\BuildLoanSchedule;
use Illuminate\Support\Carbon;

/**
 * @return array<int, array{number: int, due_date: Carbon, principal: float, interest: float, total: float}>
 */
function scheduleFor(string $method, string $period, int $months, int $interval = 30, float $rate = 5.0): array
{
    return (new BuildLoanSchedule)->handle(100000, $rate, $period, $method, $months, $interval, Carbon::parse('2026-02-01'));
}

test('a flat rate monthly loan charges the same interest on every installment', function () {
    $rows = scheduleFor('FLAT', 'per_month', 3);

    expect($rows)->toHaveCount(3)
        ->and(collect($rows)->pluck('interest')->all())->toBe([5000.0, 5000.0, 5000.0])
        ->and(round(collect($rows)->sum('principal'), 2))->toBe(100000.0)
        ->and(round(collect($rows)->sum('total'), 2))->toBe(115000.0);
});

test('installments are spaced by the repayment interval from the first due date', function () {
    $rows = scheduleFor('FLAT', 'per_month', 2, 14);

    expect($rows)->toHaveCount(4)
        ->and($rows[0]['due_date']->toDateString())->toBe('2026-02-01')
        ->and($rows[1]['due_date']->toDateString())->toBe('2026-02-15');
});

test('a rate charged once for the whole loan is split across the installments', function () {
    $rows = scheduleFor('FLAT', 'per_loan', 4, 30, 10.0);

    expect(round(collect($rows)->sum('interest'), 2))->toBe(10000.0)
        ->and(round(collect($rows)->sum('principal'), 2))->toBe(100000.0);
});

test('a reducing balance loan repays the principal with less interest than flat', function () {
    $reducing = scheduleFor('REDUCING', 'per_month', 4);
    $flat = scheduleFor('FLAT', 'per_month', 4);

    expect(round(collect($reducing)->sum('principal'), 2))->toBe(100000.0)
        ->and(collect($reducing)->sum('interest'))->toBeLessThan(collect($flat)->sum('interest'))
        ->and($reducing[0]['interest'])->toBe(5000.0)
        ->and($reducing[1]['interest'])->toBeLessThan($reducing[0]['interest']);
});

test('rounding leftovers land on the last installment', function () {
    $rows = scheduleFor('FLAT', 'per_month', 3, 30, 3.0);

    expect(round(collect($rows)->sum('principal'), 2))->toBe(100000.0)
        ->and(round(collect($rows)->sum('interest'), 2))->toBe(9000.0);
});

test('a lump sum is one installment with the interest of the whole term', function () {
    $builder = new BuildLoanSchedule;
    $due = Carbon::parse('2026-05-15');

    $monthly = $builder->lumpSum(100000, 5.0, 'per_month', 3, $due);
    $yearly = $builder->lumpSum(100000, 12.0, 'per_year', 6, $due);
    $once = $builder->lumpSum(100000, 10.0, 'per_loan', 6, $due);

    expect($monthly)->toHaveCount(1)
        ->and($monthly[0]['number'])->toBe(1)
        ->and($monthly[0]['principal'])->toBe(100000.0)
        ->and($monthly[0]['interest'])->toBe(15000.0)
        ->and($monthly[0]['total'])->toBe(115000.0)
        ->and($monthly[0]['due_date']->toDateString())->toBe('2026-05-15')
        ->and($yearly[0]['interest'])->toBe(6000.0)
        ->and($once[0]['interest'])->toBe(10000.0);
});

test('a lump sum rounds the interest to cents', function () {
    $rows = (new BuildLoanSchedule)->lumpSum(33333.33, 3.0, 'per_month', 1, Carbon::parse('2026-05-15'));

    expect($rows[0]['interest'])->toBe(1000.0)->and($rows[0]['total'])->toBe(34333.33);
});
