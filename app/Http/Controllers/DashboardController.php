<?php

namespace App\Http\Controllers;

use App\Actions\ReportManagement\BuildDashboard;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesReports;

    /**
     * The page, or the figures for one group and financial cycle as JSON. Leaders and the Treasurer get the whole group (with its
     * balance); everyone else gets their own figures. The cycle is the one covering today unless financial_cycle_id names another,
     * or "all" for all time.
     */
    public function index(Request $request, BuildDashboard $build): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.home', ['groups' => $this->groupOptions($request->user()), 'platform' => $this->platformFigures()]);
        }

        $group = $this->groupFor($request);
        $cycles = $this->cyclesOf($group);
        $cycle = $request->query('financial_cycle_id') === 'all' ? null : $this->chosenCycle($request, $group, $cycles);
        $member = $group->groupMembers()->where('user_id', $request->user()->id)->first();

        return response()->json([
            'data' => $build->handle($group, $cycle, $member, Gate::allows('managePayments', $group)),
            'group' => ['id' => $group->id, 'name' => $group->name],
            'cycle_id' => $cycle?->id ?? 'all',
            'cycles' => $this->cycleOptions($cycles),
        ]);
    }

    /**
     * Platform-wide totals, shown to platform admins only.
     *
     * @return array<string, int>|null
     */
    private function platformFigures(): ?array
    {
        if (Gate::denies('platform-admin')) {
            return null;
        }

        return [
            'groups' => Group::query()->count(),
            'users' => User::query()->count(),
            'new_users' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
