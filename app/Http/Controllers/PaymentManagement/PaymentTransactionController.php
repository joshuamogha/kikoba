<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Resources\PaymentManagement\PaymentTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentTransactionController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the page, or the group's payment transactions as JSON: all for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.payment-transactions', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = $this->canManagePayments($group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->paymentTransactions()
            ->with(['paymentMethod:id,name', 'groupMember:id,name'])
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'completed', 'failed'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('transaction_number', 'like', "%{$search}%")->orWhere('provider_transaction_id', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return PaymentTransactionResource::collection($rows)->additional(['can_manage' => $canManage])->response();
    }
}
