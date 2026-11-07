<?php

namespace App\Policies\UserManagement;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupRole;
use App\Models\UserManagement\User;

class GroupPolicy
{
    /**
     * Platform admins may do anything with any group.
     */
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Any signed-in user may start a group.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return $user->isMemberOf($group);
    }

    public function update(User $user, Group $group): bool
    {
        return $user->leadsGroup($group);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $user->leadsGroup($group);
    }

    public function manageRoles(User $user, Group $group): bool
    {
        return $user->leadsGroup($group);
    }

    public function manageSettings(User $user, Group $group): bool
    {
        return $user->leadsGroup($group);
    }

    /**
     * Leaders and the Treasurer manage cycles, funds, lookups and money records.
     */
    public function managePayments(User $user, Group $group): bool
    {
        return $user->holdsGroupRole($group, [...GroupRole::LEADERSHIP, GroupRole::TREASURER]);
    }

    /**
     * Leaders and the Treasurer review applications, disburse loans and approve repayments.
     */
    public function manageLoans(User $user, Group $group): bool
    {
        return $user->holdsGroupRole($group, [...GroupRole::LEADERSHIP, GroupRole::TREASURER]);
    }

    /**
     * Leaders and the Treasurer calculate, approve and pay out share outs.
     */
    public function manageShareOuts(User $user, Group $group): bool
    {
        return $user->holdsGroupRole($group, [...GroupRole::LEADERSHIP, GroupRole::TREASURER]);
    }
}
