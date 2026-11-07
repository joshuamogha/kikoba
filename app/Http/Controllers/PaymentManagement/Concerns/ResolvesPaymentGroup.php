<?php

namespace App\Http\Controllers\PaymentManagement\Concerns;

use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Shared by the payment-management pages: each page works on one group chosen with the group select.
 */
trait ResolvesPaymentGroup
{
    use ScopesToViewer;

    /**
     * The group named by the group_id query value, when the viewer may see it.
     */
    protected function groupFor(Request $request): Group
    {
        $group = Group::query()->findOrFail($request->integer('group_id'));

        Gate::authorize('view', $group);

        return $group;
    }

    protected function canManagePayments(Group $group): bool
    {
        return Gate::allows('managePayments', $group);
    }

    /**
     * Rows of a lookup query as select options.
     *
     * @param  Builder<*>|Relation<*, *, *>  $query
     * @return array<int, array{value: int, label: string}>
     */
    protected function options(Builder|Relation $query, string $label = 'name'): array
    {
        return $query->get(['id', $label])
            ->map(fn ($row) => ['value' => $row->id, 'label' => $row->{$label}])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function memberOptions(Group $group): array
    {
        return $group->groupMembers()->where('membership_status', 'active')->orderBy('name')->get(['id', 'name', 'member_number'])
            ->map(fn ($member) => ['value' => $member->id, 'label' => "{$member->name} ({$member->member_number})"])
            ->all();
    }

    protected function failure(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message], $status);
    }
}
