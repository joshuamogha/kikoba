<?php

namespace App\Http\Controllers\ShareManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use App\Http\Resources\ShareManagement\MemberShareOutResource;
use App\Models\ShareManagement\MemberShareOut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberShareOutListController extends Controller
{
    use ScopesToViewer;

    /**
     * Show what members are owed across the viewer's groups: everyone's in groups they manage, otherwise their own.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if (! $request->expectsJson()) {
            return view('backend.pages.share-management.member-share-outs', ['groups' => $this->groupOptions($user)]);
        }

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $query = MemberShareOut::query()
            ->with(['groupMember', 'shareOut.group:id,name', 'shareOut.financialCycle:id,name'])
            ->when($request->integer('group_id'), fn ($rows, $groupId) => $rows->whereHas('shareOut', fn ($shareOut) => $shareOut->where('group_id', $groupId)))
            ->when(in_array($status, ['pending', 'partial', 'paid'], true), fn ($rows) => $rows->where('status', $status))
            ->when($search !== '', fn ($rows) => $rows->whereHas('groupMember', fn ($member) => $member
                ->where('name', 'like', "%{$search}%")
                ->orWhere('member_number', 'like', "%{$search}%")));

        $this->restrictToMemberRows(
            $query,
            $user,
            fn ($scope, array $groupIds) => $scope->whereHas('shareOut', fn ($shareOut) => $shareOut->whereIn('group_id', $groupIds)),
            fn ($scope, int $userId) => $scope->whereHas('groupMember', fn ($member) => $member->where('user_id', $userId)),
        );

        return MemberShareOutResource::collection($query->orderByDesc('id')->paginate(15)->withQueryString())->response();
    }
}
