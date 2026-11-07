<?php

namespace App\Http\Controllers\UserManagement;

use App\Actions\UserManagement\CreateGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\StoreGroupRequest;
use App\Http\Requests\UserManagement\UpdateGroupRequest;
use App\Http\Resources\UserManagement\GroupResource;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupController extends Controller
{
    /**
     * Show the page, or the paginated list of the user's groups (all groups for platform admins with ?scope=all).
     */
    public function index(Request $request): View|JsonResponse
    {
        $canViewAll = Gate::allows('platform-admin');
        $showAll = $canViewAll && $request->query('scope') === 'all';

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.index', [
                'groupTypes' => GroupType::query()->where('is_active', true)->orderBy('display_order')->get(['id', 'name']),
                'showAll' => $showAll,
            ]);
        }

        $userId = $request->user()->id;
        $search = trim($request->string('q')->toString());

        $groups = Group::query()
            ->with([
                'groupType:id,name',
                'groupMembers' => fn ($query) => $query->where('user_id', $userId)->with([
                    'groupMemberRoles' => fn ($roles) => $roles->active()->with('groupRole:id,name'),
                ]),
            ])
            ->withCount(['groupMembers' => fn ($query) => $query->where('membership_status', '!=', 'exited')])
            ->when(! $showAll, fn ($query) => $query->whereHas('groupMembers', fn ($members) => $members
                ->where('user_id', $userId)->where('membership_status', 'active')))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return GroupResource::collection($groups)->response();
    }

    /**
     * Create a group; the creator becomes its founding Chairperson.
     */
    public function store(StoreGroupRequest $request, CreateGroup $createGroup): JsonResponse
    {
        $group = $createGroup->handle($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Group created. You are its Chairperson.',
            'data' => new GroupResource($group),
            'redirect' => route('groups.show', $group),
        ], 201);
    }

    /**
     * Overview page of a group.
     */
    public function show(Request $request, Group $group): View
    {
        Gate::authorize('view', $group);

        $group->load('groupType', 'creator')
            ->loadCount(['groupMembers' => fn ($query) => $query->where('membership_status', '!=', 'exited')]);

        $myRoles = $group->groupMembers()
            ->where('user_id', $request->user()->id)
            ->first()?->groupMemberRoles()->active()->with('groupRole:id,name')->get()
            ->pluck('groupRole.name') ?? collect();

        return view('backend.pages.groups.show', [
            'group' => $group,
            'myRoles' => $myRoles,
            'canManage' => Gate::allows('manageMembers', $group),
        ]);
    }

    /**
     * Update the group's details.
     */
    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $group->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Group updated successfully.',
            'data' => new GroupResource($group->load('groupType:id,name')),
        ]);
    }
}
