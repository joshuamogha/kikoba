<?php

namespace App\Http\Controllers\ShareManagement\Concerns;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides which share-out records a person may list across groups.
 * Platform admins see everything, managers see everyone's rows in their groups, members see their own rows.
 */
trait ScopesToViewer
{
    /**
     * Groups offered in the group filter, as select options.
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected function groupOptions(User $user): array
    {
        return Group::query()
            ->when(! $user->is_platform_admin, fn ($query) => $query->whereIn('id', $user->memberGroupIds()))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($group) => ['value' => $group->id, 'label' => $group->name])
            ->all();
    }

    /**
     * Limit group-level records (share outs) to the viewer's groups.
     *
     * @param  Builder<*>  $query
     */
    protected function restrictToGroups(Builder $query, User $user, string $groupColumn = 'group_id'): void
    {
        if (! $user->is_platform_admin) {
            $query->whereIn($groupColumn, $user->memberGroupIds());
        }
    }

    /**
     * Limit records that belong to a member: managers see their groups' rows, everyone else only their own.
     *
     * @param  Builder<*>  $query
     * @param  callable(Builder<*>, array<int, int>): mixed  $inManagedGroups  adds the "in a managed group" condition
     * @param  callable(Builder<*>, int): mixed  $ownedBy  adds the "belongs to this user" condition
     */
    protected function restrictToMemberRows(Builder $query, User $user, callable $inManagedGroups, callable $ownedBy): void
    {
        if ($user->is_platform_admin) {
            return;
        }

        $managed = $user->managedGroupIds()->all();

        $query->where(function (Builder $scope) use ($user, $managed, $inManagedGroups, $ownedBy): void {
            $scope->where(fn (Builder $inner) => $inManagedGroups($inner, $managed))
                ->orWhere(fn (Builder $inner) => $ownedBy($inner, $user->id));
        });
    }
}
