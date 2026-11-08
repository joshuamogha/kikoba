<?php

namespace App\Http\Controllers\PaymentManagement\Concerns;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\UserManagement\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The financial cycles a page lets the viewer pick from: open cycles first, then drafts, then closed ones, newest first within each.
 */
trait ListsFinancialCycles
{
    /**
     * @return Collection<int, FinancialCycle>
     */
    protected function cyclesOf(Group $group): Collection
    {
        return $group->financialCycles()
            ->orderByRaw("case status when 'open' then 0 when 'draft' then 1 else 2 end")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The cycle named by the financial_cycle_id input, otherwise the open cycle covering today, then any cycle covering today,
     * otherwise the first in the list.
     *
     * @param  Collection<int, FinancialCycle>  $cycles
     */
    protected function chosenCycle(Request $request, Group $group, Collection $cycles): ?FinancialCycle
    {
        if ($request->filled('financial_cycle_id')) {
            return $group->financialCycles()->findOrFail($request->integer('financial_cycle_id'));
        }

        $coversToday = $cycles->filter(fn (FinancialCycle $cycle) => $cycle->start_date->lte(today()) && $cycle->end_date->gte(today()));

        return $coversToday->firstWhere('status', 'open') ?? $coversToday->first() ?? $cycles->first();
    }

    /**
     * The cycles as select options.
     *
     * @param  Collection<int, FinancialCycle>  $cycles
     * @return array<int, array{value: int, label: string}>
     */
    protected function cycleOptions(Collection $cycles): array
    {
        return $cycles->map(fn (FinancialCycle $cycle) => ['value' => $cycle->id, 'label' => "{$cycle->name} ({$cycle->start_date->format('M Y')} - {$cycle->end_date->format('M Y')})"])->all();
    }
}
