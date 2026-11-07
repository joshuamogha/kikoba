<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\StoreRelationRequest;
use App\Http\Requests\UserManagement\UpdateRelationRequest;
use App\Http\Resources\UserManagement\RelationResource;
use App\Models\UserManagement\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RelationController extends Controller
{
    /**
     * Show the page, or the paginated list (optionally filtered by name) when requested as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.relations.index');
        }

        $search = trim($request->string('q')->toString());

        $relations = Relation::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return RelationResource::collection($relations)->response();
    }

    /**
     * Create a relation, placed last in the display order.
     */
    public function store(StoreRelationRequest $request): JsonResponse
    {
        $relation = Relation::query()->create([
            ...$request->validated(),
            'is_active' => true,
            'display_order' => (int) Relation::query()->max('display_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Relation created successfully.',
            'data' => new RelationResource($relation),
        ], 201);
    }

    /**
     * Update a relation.
     */
    public function update(UpdateRelationRequest $request, Relation $relation): JsonResponse
    {
        $relation->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Relation updated successfully.',
            'data' => new RelationResource($relation),
        ]);
    }

    /**
     * Toggle whether a relation can be chosen.
     */
    public function activate(Relation $relation): JsonResponse
    {
        $relation->update(['is_active' => ! $relation->is_active]);

        return response()->json([
            'success' => true,
            'message' => $relation->is_active ? 'Relation activated.' : 'Relation deactivated.',
        ]);
    }

    /**
     * Delete a relation.
     */
    public function destroy(Relation $relation): JsonResponse
    {
        $relation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Relation deleted successfully.',
        ]);
    }
}
