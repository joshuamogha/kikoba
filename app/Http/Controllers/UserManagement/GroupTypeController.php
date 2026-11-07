<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\StoreGroupTypeRequest;
use App\Http\Requests\UserManagement\UpdateGroupTypeRequest;
use App\Http\Resources\UserManagement\GroupTypeResource;
use App\Models\UserManagement\GroupType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupTypeController extends Controller
{
    /**
     * Show the page, or the paginated list (optionally filtered by name) when requested as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.group-types.index');
        }

        $search = trim($request->string('q')->toString());

        $groupTypes = GroupType::query()
            ->withCount('groups')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return GroupTypeResource::collection($groupTypes)->response();
    }

    /**
     * Create a group type, placed last in the display order.
     */
    public function store(StoreGroupTypeRequest $request): JsonResponse
    {
        $groupType = GroupType::query()->create([
            ...$request->validated(),
            'is_active' => true,
            'display_order' => (int) GroupType::query()->max('display_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Group type created successfully.',
            'data' => new GroupTypeResource($groupType),
        ], 201);
    }

    /**
     * Update a group type.
     */
    public function update(UpdateGroupTypeRequest $request, GroupType $groupType): JsonResponse
    {
        $groupType->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Group type updated successfully.',
            'data' => new GroupTypeResource($groupType),
        ]);
    }

    /**
     * Toggle whether a group type can be chosen for new groups.
     */
    public function activate(GroupType $groupType): JsonResponse
    {
        $groupType->update(['is_active' => ! $groupType->is_active]);

        return response()->json([
            'success' => true,
            'message' => $groupType->is_active ? 'Group type activated.' : 'Group type deactivated.',
        ]);
    }

    /**
     * Delete a group type that no group uses.
     */
    public function destroy(GroupType $groupType): JsonResponse
    {
        $groupsCount = $groupType->groups()->count();

        if ($groupsCount > 0) {
            return response()->json([
                'success' => false,
                'error' => "This group type is used by {$groupsCount} group(s). Deactivate it instead.",
            ], 422);
        }

        $groupType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Group type deleted successfully.',
        ]);
    }
}
