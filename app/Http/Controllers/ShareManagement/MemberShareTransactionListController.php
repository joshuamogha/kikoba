<?php

namespace App\Http\Controllers\ShareManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use App\Http\Resources\ShareManagement\MemberShareTransactionResource;
use App\Models\ShareManagement\MemberShareTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberShareTransactionListController extends Controller
{
    use ScopesToViewer;

    /**
     * Show share purchases across the viewer's groups: everyone's in groups they manage, otherwise their own.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if (! $request->expectsJson()) {
            return view('backend.pages.share-management.member-share-transactions', ['groups' => $this->groupOptions($user)]);
        }

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $query = MemberShareTransaction::query()
            ->with(['group:id,name', 'financialCycle:id,name', 'groupMember'])
            ->when($request->integer('group_id'), fn ($rows, $groupId) => $rows->where('group_id', $groupId))
            ->when(in_array($status, ['pending', 'approved'], true), fn ($rows) => $rows->where('status', $status))
            ->when($search !== '', fn ($rows) => $rows->where(fn ($inner) => $inner
                ->where('transaction_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('member_number', 'like', "%{$search}%"))));

        $this->restrictToMemberRows(
            $query,
            $user,
            fn ($scope, array $groupIds) => $scope->whereIn('group_id', $groupIds),
            fn ($scope, int $userId) => $scope->whereHas('groupMember', fn ($member) => $member->where('user_id', $userId)),
        );

        $totals = $query->clone()->toBase()->reorder()->selectRaw('coalesce(sum(number_of_shares), 0) as shares, coalesce(sum(total_amount), 0) as amount')->first();

        return MemberShareTransactionResource::collection($query->orderByDesc('transaction_date')->orderByDesc('id')->paginate(15)->withQueryString())
            ->additional(['totals' => ['shares' => (int) $totals->shares, 'amount' => (float) $totals->amount]])
            ->response();
    }
}
