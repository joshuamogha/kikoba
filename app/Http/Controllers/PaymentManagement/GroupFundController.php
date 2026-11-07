<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreGroupFundRequest;
use App\Http\Requests\PaymentManagement\UpdateGroupFundRequest;
use App\Http\Resources\PaymentManagement\GroupFundResource;
use App\Models\PaymentManagement\GroupFund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GroupFundController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the page, or the group's funds (with fund type and frequency options) as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.group-funds', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $funds = $group->groupFunds()
            ->with(['fundType:id,name', 'contributionFrequency:id,name'])
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return GroupFundResource::collection($funds)->additional([
            'can_manage' => $this->canManagePayments($group),
            'options' => [
                'fund_types' => $this->options($group->fundTypes()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'frequencies' => $this->options($group->contributionFrequencies()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ],
        ])->response();
    }

    public function store(StoreGroupFundRequest $request): JsonResponse
    {
        $fund = $request->group()->groupFunds()->create([
            ...$request->validated(),
            'code' => Str::upper($request->validated('code')),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return $this->saved($fund, 'Fund created successfully.', 201);
    }

    public function update(UpdateGroupFundRequest $request, GroupFund $groupFund): JsonResponse
    {
        abort_unless($groupFund->group_id === $request->group()->id, 404);

        $groupFund->update([...$request->validated(), 'code' => Str::upper($request->validated('code'))]);

        return $this->saved($groupFund, 'Fund updated successfully.');
    }

    private function saved(GroupFund $fund, string $message, int $status = 200): JsonResponse
    {
        $fund->load(['fundType:id,name', 'contributionFrequency:id,name']);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new GroupFundResource($fund))->resolve()], $status);
    }
}
