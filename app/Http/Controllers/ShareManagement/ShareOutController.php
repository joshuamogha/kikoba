<?php

namespace App\Http\Controllers\ShareManagement;

use App\Actions\ShareManagement\CalculateShareOut;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShareManagement\StoreShareOutRequest;
use App\Http\Requests\ShareManagement\UpdateShareOutRequest;
use App\Http\Resources\ShareManagement\ShareOutResource;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use App\Models\UserManagement\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShareOutController extends Controller
{
    /**
     * Show the share outs page, or the group's share outs as JSON.
     */
    public function index(Request $request, Group $group): View|JsonResponse
    {
        Gate::authorize('view', $group);

        $canManage = Gate::allows('manageShareOuts', $group);

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.share-outs.index', [
                'group' => $group,
                'canManage' => $canManage,
                'cycles' => $canManage ? $this->cyclesWithoutShareOut($group) : [],
            ]);
        }

        $shareOuts = $group->shareOuts()
            ->with('financialCycle:id,name,start_date,end_date')
            ->withCount('memberShareOuts')
            ->withSum('memberShareOuts as member_share_outs_sum_amount_paid', 'amount_paid')
            ->orderByDesc('calculation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return ShareOutResource::collection($shareOuts)->response();
    }

    /**
     * Calculate the share out of a closed cycle. It starts as a draft.
     */
    public function store(StoreShareOutRequest $request, Group $group, CalculateShareOut $calculate): JsonResponse
    {
        $cycle = $group->financialCycles()->findOrFail($request->integer('financial_cycle_id'));

        if ($cycle->shareOut()->exists()) {
            return $this->failure('financial_cycle_id', 'This cycle already has a share out.');
        }

        $shareOut = $calculate->handle($cycle, $request->user(), $request->input('calculation_date'));

        return response()->json([
            'message' => 'Share out calculated. Review it, then approve it to start paying members.',
            'url' => route('groups.share-outs.show', [$group, $shareOut]),
        ], 201);
    }

    /**
     * Show a share out page, or its totals with the member rows the viewer may see as JSON.
     */
    public function show(Request $request, Group $group, ShareOut $shareOut): View|JsonResponse
    {
        Gate::authorize('view', $group);

        $canManage = Gate::allows('manageShareOuts', $group);

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.share-outs.show', [
                'group' => $group,
                'shareOut' => $shareOut,
                'canManage' => $canManage,
                'paymentMethods' => $group->paymentMethods()->where('is_active', true)->orderBy('display_order')->get(['id', 'name']),
            ]);
        }

        // Only people who manage share outs see everyone's figures; a member sees their own row.
        $shareOut->load([
            'financialCycle:id,name,start_date,end_date',
            'memberShareOuts' => fn ($query) => $query
                ->with(['groupMember', 'shareOutPayments' => fn ($payments) => $payments->with('paymentMethod:id,name')->orderBy('payment_date')->orderBy('id')])
                ->when(! $canManage, fn ($rows) => $rows->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
                ->orderBy('group_member_id'),
        ]);

        return (new ShareOutResource($shareOut))->response();
    }

    /**
     * Recalculate a draft share out with the latest figures.
     */
    public function update(UpdateShareOutRequest $request, Group $group, ShareOut $shareOut, CalculateShareOut $calculate): JsonResponse
    {
        $calculate->handle($shareOut->financialCycle, $request->user(), $request->input('calculation_date'));

        return response()->json(['message' => 'Share out recalculated.']);
    }

    /**
     * Approve a draft share out: the figures are locked and members can be paid.
     */
    public function approve(Request $request, Group $group, ShareOut $shareOut): JsonResponse
    {
        Gate::authorize('manageShareOuts', $group);

        if ($shareOut->status !== ShareOut::DRAFT) {
            return $this->failure('status', 'Only a draft share out can be approved.');
        }

        if (! $shareOut->memberShareOuts()->exists()) {
            return $this->failure('status', 'There are no members to pay in this share out.');
        }

        DB::transaction(function () use ($request, $shareOut): void {
            $shareOut->update([
                'status' => ShareOut::APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // Members who are owed nothing (loans and fines used up their savings) need no payment.
            $shareOut->memberShareOuts()->where('net_payable', 0)->update(['status' => MemberShareOut::PAID]);

            $shareOut->refresh();
            $shareOut->completeWhenAllPaid();
        });

        return response()->json(['message' => 'Share out approved.']);
    }

    /**
     * Delete a draft share out so it can be calculated again.
     */
    public function destroy(Group $group, ShareOut $shareOut): JsonResponse
    {
        Gate::authorize('manageShareOuts', $group);

        if ($shareOut->status !== ShareOut::DRAFT) {
            return $this->failure('status', 'Only a draft share out can be deleted.');
        }

        $shareOut->delete();

        return response()->json(['message' => 'Draft share out deleted.']);
    }

    /**
     * Closed cycles that have no share out yet.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function cyclesWithoutShareOut(Group $group): array
    {
        return $group->financialCycles()
            ->where('status', 'closed')
            ->whereDoesntHave('shareOut')
            ->orderByDesc('cycle_number')
            ->get(['id', 'name', 'start_date', 'end_date'])
            ->map(fn ($cycle) => ['value' => $cycle->id, 'label' => "{$cycle->name} ({$cycle->start_date} – {$cycle->end_date})"])
            ->all();
    }

    private function failure(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
