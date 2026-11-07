<?php

namespace App\Http\Controllers\SettingManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Resources\SettingManagement\FinePaymentResource;
use App\Models\PaymentManagement\FinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FinePaymentController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * The Fines settings page, or the group's fine payments as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('setting-pages.show', ['page' => 'fines']);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('managePayments', $group);
        $search = trim($request->string('q')->toString());

        $rows = FinePayment::query()
            ->whereHas('memberFine', fn ($fine) => $fine->where('group_id', $group->id))
            ->with(['memberFine.groupMember', 'memberFine.fineType', 'paymentMethod'])
            ->when(! $canManage, fn ($query) => $query->whereHas('memberFine.groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('payment_number', 'like', "%{$search}%")
                ->orWhereHas('memberFine', fn ($fine) => $fine->where('fine_number', 'like', "%{$search}%")
                    ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")))))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return FinePaymentResource::collection($rows)->additional(['can_manage' => $canManage])->response();
    }
}
