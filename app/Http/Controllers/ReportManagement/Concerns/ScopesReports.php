<?php

namespace App\Http\Controllers\ReportManagement\Concerns;

use App\Http\Controllers\PaymentManagement\Concerns\ListsFinancialCycles;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Models\UserManagement\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * What every report needs: the group chosen with group_id and whether the viewer sees everyone (leaders and the Treasurer)
 * or only their own records (everyone else).
 */
trait ScopesReports
{
    use ListsFinancialCycles;
    use ResolvesPaymentGroup;

    protected function canSeeEveryone(Group $group): bool
    {
        return Gate::allows('managePayments', $group);
    }

    /**
     * The id of the viewer's own membership in the group, or null.
     */
    protected function ownMemberId(Request $request, Group $group): ?int
    {
        return $group->groupMembers()->where('user_id', $request->user()->id)->value('id');
    }

    /**
     * A member label such as "Asha Juma (M-0001)".
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected function allMemberOptions(Group $group): array
    {
        return $group->groupMembers()->orderBy('name')->get(['id', 'name', 'member_number'])
            ->map(fn ($member) => ['value' => $member->id, 'label' => "{$member->name} ({$member->member_number})"])->all();
    }
}
