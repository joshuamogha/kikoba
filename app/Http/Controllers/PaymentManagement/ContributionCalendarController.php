<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\BuildContributionCalendar;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Models\PaymentManagement\FinancialCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContributionCalendarController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the page, or the month-by-month grid of a cycle as JSON: every member for leaders and the Treasurer, otherwise the viewer's own row.
     */
    public function index(Request $request, BuildContributionCalendar $build): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.contribution-calendar', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('managePayments', $group);
        $type = in_array($request->string('type')->toString(), BuildContributionCalendar::TYPES, true) ? $request->string('type')->toString() : 'contributions';

        $ownMemberId = $group->groupMembers()->where('user_id', $request->user()->id)->value('id');
        $requestedMemberId = $request->integer('group_member_id') ?: null;

        abort_unless($canManage || $requestedMemberId === null || $requestedMemberId === $ownMemberId, 403);

        $onlyMemberId = $canManage ? $requestedMemberId : ($ownMemberId ?? 0);

        if ($requestedMemberId !== null) {
            abort_unless($group->groupMembers()->whereKey($requestedMemberId)->exists(), 404);
        }

        $cycles = $group->financialCycles()
            ->orderByRaw("case status when 'open' then 0 when 'draft' then 1 else 2 end")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $cycle = $request->filled('financial_cycle_id')
            ? $group->financialCycles()->findOrFail($request->integer('financial_cycle_id'))
            : $this->defaultCycle($cycles);

        return response()->json([
            'data' => $cycle === null ? null : [
                'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'start_date' => $cycle->start_date->toDateString(), 'end_date' => $cycle->end_date->toDateString()],
                'type' => $type,
                'currency' => $group->currency,
                ...$build->handle($group, $cycle, $type, $onlyMemberId),
            ],
            'cycles' => $cycles->map(fn (FinancialCycle $item) => ['value' => $item->id, 'label' => "{$item->name} ({$item->start_date->format('M Y')} - {$item->end_date->format('M Y')})"])->all(),
            'can_manage' => $canManage,
        ]);
    }

    /**
     * The open cycle covering today if there is one, then any cycle covering today, otherwise the first in the list (open cycles first).
     *
     * @param  Collection<int, FinancialCycle>  $cycles
     */
    private function defaultCycle($cycles): ?FinancialCycle
    {
        $coversToday = $cycles->filter(fn (FinancialCycle $cycle) => $cycle->start_date->lte(today()) && $cycle->end_date->gte(today()));

        return $coversToday->firstWhere('status', 'open') ?? $coversToday->first() ?? $cycles->first();
    }
}
