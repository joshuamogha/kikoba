<?php

namespace App\Http\Controllers\LoanManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Resources\LoanManagement\LoanInstallmentResource;
use App\Models\LoanManagement\LoanInstallment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanInstallmentController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the page, or the group's installments as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-installments', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageLoans', $group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $query = LoanInstallment::query()
            ->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))
            ->with('loan.groupMember')
            ->when(! $canManage, fn ($query) => $query->whereHas('loan.groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'partial', 'paid', 'overdue'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas('loan', fn ($loan) => $loan
                ->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%"))))
            ->orderBy('due_date')
            ->orderBy('id');

        $matching = $query->clone()->reorder()->toBase();
        $due = round((float) (clone $matching)->sum('total_due'), 2);
        $paid = round((float) (clone $matching)->sum('total_paid'), 2);

        $rows = $query->paginate(15)->withQueryString();

        return LoanInstallmentResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'totals' => ['amount' => $due, 'paid' => $paid, 'balance' => round($due - $paid, 2)],
        ])->response();
    }
}
