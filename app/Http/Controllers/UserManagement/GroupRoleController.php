<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\StoreGroupRoleRequest;
use App\Http\Requests\UserManagement\UpdateGroupRoleRequest;
use App\Http\Resources\UserManagement\GroupRoleResource;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupRoleController extends Controller
{
    /**
     * Show the roles page, or the system roles plus this group's custom roles as JSON.
     */
    public function index(Request $request, Group $group): View|JsonResponse
    {
        Gate::authorize('view', $group);

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.roles', [
                'group' => $group,
                'canManage' => Gate::allows('manageRoles', $group),
            ]);
        }

        $roles = GroupRole::query()
            ->where(fn ($query) => $query->whereNull('group_id')->orWhere('group_id', $group->id))
            ->withCount(['groupMemberRoles' => fn ($query) => $query
                ->active()
                ->whereHas('groupMember', fn ($member) => $member->where('group_id', $group->id))])
            ->orderByRaw('group_id is not null')
            ->orderBy('id')
            ->get();

        return GroupRoleResource::collection($roles)->response();
    }

    /**
     * Create a custom role for the group.
     */
    public function store(StoreGroupRoleRequest $request, Group $group): JsonResponse
    {
        $role = $group->groupRoles()->create([
            ...$request->validated(),
            'is_system_role' => false,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => new GroupRoleResource($role),
        ], 201);
    }

    /**
     * Update a custom role. System roles never resolve here (route binding is scoped to the group).
     */
    public function update(UpdateGroupRoleRequest $request, Group $group, GroupRole $groupRole): JsonResponse
    {
        $groupRole->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => new GroupRoleResource($groupRole),
        ]);
    }

    /**
     * Delete a custom role that has never been assigned; deactivate it otherwise.
     */
    public function destroy(Group $group, GroupRole $groupRole): JsonResponse
    {
        Gate::authorize('manageRoles', $group);

        if ($groupRole->groupMemberRoles()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'This role has been assigned to members, so it is kept for history. Deactivate it instead.',
            ], 422);
        }

        $groupRole->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted successfully.']);
    }
}
