<?php

namespace App\Actions\PaymentManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\UserManagement\Group;
use Illuminate\Support\Carbon;

class ResolveFinancialCycle
{
    /**
     * The cycle that covers the date: an open one first, then a draft one (which is opened, because the leaders defined it for these
     * months), then any other, such as a closed cycle when old data is back-captured. When no cycle covers the date, a calendar-year
     * cycle is opened, shortened so that it never overlaps an existing cycle.
     */
    public function handle(Group $group, Carbon $date, int $userId): FinancialCycle
    {
        $covering = $group->financialCycles()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        $existing = $covering->firstWhere('status', 'open') ?? $covering->firstWhere('status', 'draft') ?? $covering->first();

        if ($existing !== null) {
            if ($existing->status === 'draft') {
                $existing->update(['status' => 'open', 'opened_by' => $userId]);
            }

            return $existing;
        }

        $start = $date->copy()->startOfYear();
        $end = $date->copy()->endOfYear();

        $previousEnd = $group->financialCycles()->whereDate('end_date', '<', $date)->whereDate('end_date', '>=', $start)->max('end_date');
        $nextStart = $group->financialCycles()->whereDate('start_date', '>', $date)->whereDate('start_date', '<=', $end)->min('start_date');

        if ($previousEnd !== null) {
            $start = Carbon::parse($previousEnd)->addDay();
        }

        if ($nextStart !== null) {
            $end = Carbon::parse($nextStart)->subDay();
        }

        return $group->financialCycles()->create([
            'contribution_frequency_id' => $group->ensureGroupSetting()->contribution_frequency_id,
            'name' => 'Cycle '.$date->year,
            'cycle_number' => $group->financialCycles()->max('cycle_number') + 1,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'open',
            'opened_by' => $userId,
        ]);
    }
}
