<?php

namespace App\Actions\PaymentManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\UserManagement\Group;
use Illuminate\Support\Carbon;

class ResolveFinancialCycle
{
    /**
     * The open cycle that covers the date; a calendar-year cycle is opened when the group has none.
     */
    public function handle(Group $group, Carbon $date, int $userId): FinancialCycle
    {
        $existing = $group->financialCycles()
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        return $existing ?? $group->financialCycles()->create([
            'contribution_frequency_id' => $group->ensureGroupSetting()->contribution_frequency_id,
            'name' => 'Cycle '.$date->year,
            'cycle_number' => $group->financialCycles()->max('cycle_number') + 1,
            'start_date' => $date->copy()->startOfYear()->toDateString(),
            'end_date' => $date->copy()->endOfYear()->toDateString(),
            'status' => 'open',
            'opened_by' => $userId,
        ]);
    }
}
