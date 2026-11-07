<?php

namespace App\Http\Controllers\ShareManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use App\Http\Resources\ShareManagement\ShareOutPaymentResource;
use App\Models\ShareManagement\ShareOutPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShareOutPaymentListController extends Controller
{
    use ScopesToViewer;

    /**
     * Show share-out payments across the viewer's groups: everyone's in groups they manage, otherwise their own.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if (! $request->expectsJson()) {
            return view('backend.pages.share-management.share-out-payments', ['groups' => $this->groupOptions($user)]);
        }

        $search = trim($request->string('q')->toString());

        $query = ShareOutPayment::query()
            ->with(['paymentMethod:id,name', 'memberShareOut.groupMember', 'memberShareOut.shareOut.group:id,name'])
            ->when($request->integer('group_id'), fn ($rows, $groupId) => $rows->whereHas('memberShareOut.shareOut', fn ($shareOut) => $shareOut->where('group_id', $groupId)))
            ->when($search !== '', fn ($rows) => $rows->where(fn ($inner) => $inner
                ->where('payment_number', 'like', "%{$search}%")
                ->orWhere('external_reference', 'like', "%{$search}%")
                ->orWhereHas('memberShareOut.groupMember', fn ($member) => $member
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('member_number', 'like', "%{$search}%"))));

        $this->restrictToMemberRows(
            $query,
            $user,
            fn ($scope, array $groupIds) => $scope->whereHas('memberShareOut.shareOut', fn ($shareOut) => $shareOut->whereIn('group_id', $groupIds)),
            fn ($scope, int $userId) => $scope->whereHas('memberShareOut.groupMember', fn ($member) => $member->where('user_id', $userId)),
        );

        return ShareOutPaymentResource::collection($query->orderByDesc('payment_date')->orderByDesc('id')->paginate(15)->withQueryString())->response();
    }
}
