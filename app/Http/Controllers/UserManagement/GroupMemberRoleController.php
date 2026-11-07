<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\AssignMemberRoleRequest;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\GroupMemberRole;
use App\Models\UserManagement\GroupRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GroupMemberRoleController extends Controller
{
    /**
     * Give a member a role. Giving someone Chairperson hands the role over from the current chair.
     */
    public function store(AssignMemberRoleRequest $request, Group $group, GroupMember $groupMember): JsonResponse
    {
        if ($groupMember->membership_status !== 'active') {
            return response()->json([
                'message' => 'Only active members can hold roles.',
                'errors' => ['group_role_id' => ['Only active members can hold roles.']],
            ], 422);
        }

        $role = GroupRole::query()->findOrFail($request->integer('group_role_id'));

        if (! $groupMember->isLinked() && $role->group_id === null && in_array($role->name, GroupRole::LEADERSHIP, true)) {
            return response()->json([
                'message' => 'Link their account first: leaders must be able to sign in.',
                'errors' => ['group_role_id' => ['Link their account first: leaders must be able to sign in.']],
            ], 422);
        }

        if ($groupMember->groupMemberRoles()->active()->where('group_role_id', $role->id)->exists()) {
            return response()->json([
                'message' => 'The member already holds this role.',
                'errors' => ['group_role_id' => ['The member already holds this role.']],
            ], 422);
        }

        $handedOver = false;

        DB::transaction(function () use ($request, $group, $groupMember, $role, &$handedOver): void {
            if ($role->group_id === null && $role->name === GroupRole::CHAIRPERSON) {
                $previous = $group->activeRoleAssignments(GroupRole::CHAIRPERSON)->get();
                $previous->each->end();
                $handedOver = $previous->isNotEmpty();
            }

            GroupMemberRole::query()->create([
                'group_member_id' => $groupMember->id,
                'group_role_id' => $role->id,
                'starts_at' => now()->toDateString(),
                'status' => 'active',
                'assigned_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => $handedOver ? 'Role assigned. The previous Chairperson no longer holds it.' : 'Role assigned.',
        ], 201);
    }

    /**
     * End a role assignment (kept as history). The only Chairperson cannot be removed.
     */
    public function destroy(Group $group, GroupMember $groupMember, GroupMemberRole $groupMemberRole): JsonResponse
    {
        Gate::authorize('manageRoles', $group);

        $role = $groupMemberRole->groupRole;

        if ($groupMemberRole->status !== 'active') {
            return response()->json(['success' => false, 'error' => 'This role has already ended.'], 422);
        }

        if ($role->name === GroupRole::MEMBER && $role->group_id === null) {
            return response()->json(['success' => false, 'error' => 'The Member role ends when the person leaves the group.'], 422);
        }

        if ($role->group_id === null
            && $role->name === GroupRole::CHAIRPERSON
            && $group->activeRoleAssignments(GroupRole::CHAIRPERSON)->count() <= 1) {
            return response()->json(['success' => false, 'error' => 'A group must keep a Chairperson. Assign another Chairperson first.'], 422);
        }

        $groupMemberRole->end();

        return response()->json(['success' => true, 'message' => 'Role ended.']);
    }
}
