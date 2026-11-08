<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\BuildContributionCalendar;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ListsFinancialCycles;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContributionCalendarController extends Controller
{
    use ListsFinancialCycles;
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

        $cycles = $this->cyclesOf($group);
        $cycle = $this->chosenCycle($request, $group, $cycles);

        return response()->json([
            'data' => $cycle === null ? null : [
                'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'start_date' => $cycle->start_date->toDateString(), 'end_date' => $cycle->end_date->toDateString()],
                'type' => $type,
                'currency' => $group->currency,
                ...$build->handle($group, $cycle, $type, $onlyMemberId),
            ],
            'cycles' => $this->cycleOptions($cycles),
            'can_manage' => $canManage,
        ]);
    }
}
