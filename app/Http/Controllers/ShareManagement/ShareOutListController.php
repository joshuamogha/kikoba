<?php

namespace App\Http\Controllers\ShareManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShareManagement\Concerns\ScopesToViewer;
use App\Http\Resources\ShareManagement\ShareOutResource;
use App\Models\ShareManagement\ShareOut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShareOutListController extends Controller
{
    use ScopesToViewer;

    /**
     * Show the share outs of every group the viewer belongs to, as a page or as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if (! $request->expectsJson()) {
            return view('backend.pages.share-management.share-outs', ['groups' => $this->groupOptions($user)]);
        }

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $query = ShareOut::query()
            ->with(['group:id,name', 'financialCycle:id,name,start_date,end_date'])
            ->withCount('memberShareOuts')
            ->withSum('memberShareOuts as member_share_outs_sum_amount_paid', 'amount_paid')
            ->when($request->integer('group_id'), fn ($shareOuts, $groupId) => $shareOuts->where('group_id', $groupId))
            ->when(in_array($status, ['draft', 'approved', 'completed'], true), fn ($shareOuts) => $shareOuts->where('status', $status))
            ->when($search !== '', fn ($shareOuts) => $shareOuts->where(fn ($inner) => $inner
                ->where('share_out_number', 'like', "%{$search}%")
                ->orWhereHas('financialCycle', fn ($cycle) => $cycle->where('name', 'like', "%{$search}%"))));

        $this->restrictToGroups($query, $user);

        return ShareOutResource::collection($query->orderByDesc('calculation_date')->orderByDesc('id')->paginate(15)->withQueryString())->response();
    }
}
