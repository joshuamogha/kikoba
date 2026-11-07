<?php

namespace App\Http\Controllers\SettingManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\SettingManagement\StoreFineTypeRequest;
use App\Http\Requests\SettingManagement\UpdateFineTypeRequest;
use App\Http\Resources\SettingManagement\FineTypeResource;
use App\Models\SettingManagement\FineType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class FineTypeController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * The Fines settings page, or the group's fine types as JSON.
     */
    public function index(Request $request): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('setting-pages.show', ['page' => 'fines']);
        }

        $group = $this->groupFor($request);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->fineTypes()
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return FineTypeResource::collection($rows)->additional(['can_manage' => Gate::allows('managePayments', $group)])->response();
    }

    public function store(StoreFineTypeRequest $request): JsonResponse
    {
        $fineType = $request->group()->fineTypes()->create([
            ...$request->validated(),
            'code' => Str::upper($request->validated('code')),
            'status' => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Fine type created successfully.', 'data' => (new FineTypeResource($fineType))->resolve()], 201);
    }

    public function update(UpdateFineTypeRequest $request, FineType $fineType): JsonResponse
    {
        abort_unless($fineType->group_id === $request->group()->id, 404);

        $fineType->update([...$request->validated(), 'code' => Str::upper($request->validated('code'))]);

        return response()->json(['success' => true, 'message' => 'Fine type updated successfully.', 'data' => (new FineTypeResource($fineType))->resolve()]);
    }
}
