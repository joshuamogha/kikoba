<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The month a payment is for. A period is written YYYY-MM and stored as the first and last day of that month.
 */
class BillingPeriod
{
    /**
     * Validation rules for an optional period input.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->addYear()->format('Y-m'), 'after_or_equal:2000-01'];
    }

    /**
     * The first day of the period, or of the fallback date's month when no period was given.
     */
    public static function start(?string $period, Carbon $fallback): Carbon
    {
        return $period === null || $period === ''
            ? $fallback->copy()->startOfMonth()
            : Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
    }
}
