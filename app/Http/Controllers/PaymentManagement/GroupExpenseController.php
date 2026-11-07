<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreGroupExpenseRequest;
use App\Http\Resources\PaymentManagement\GroupExpenseResource;
use App\Models\PaymentManagement\GroupExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupExpenseController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupFund', 'groupExpenseCategory', 'paymentMethod'];

    /**
     * Show the page, or the group's expenses (with form options for managers) as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.group-expenses', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = $this->canManagePayments($group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->groupExpenses()
            ->with(self::RELATIONS)
            ->when(in_array($status, ['draft', 'approved', 'paid'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('expense_number', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhere('payee_name', 'like', "%{$search}%")))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return GroupExpenseResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => $canManage ? [
                'funds' => $this->options($group->groupFunds()->where('status', 'active')->orderBy('name')),
                'categories' => $this->options($group->groupExpenseCategories()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ] : null,
        ])->response();
    }

    public function store(StoreGroupExpenseRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $group = $request->group();
        $date = Carbon::parse($request->validated('expense_date'));

        $expense = DB::transaction(function () use ($request, $group, $date, $resolveCycle): GroupExpense {
            $expense = $group->groupExpenses()->create([
                ...$request->validated(),
                'financial_cycle_id' => $resolveCycle->handle($group, $date, $request->user()->id)->id,
                'expense_number' => 'PENDING-'.uniqid(),
                'status' => 'draft',
                'requested_by' => $request->user()->id,
            ]);
            $expense->update(['expense_number' => sprintf('EXP-%d-%06d', $date->year, $expense->id)]);

            return $expense;
        });

        return $this->saved($expense, 'Expense recorded successfully.', 201);
    }

    public function approve(Request $request, GroupExpense $groupExpense): JsonResponse
    {
        Gate::authorize('managePayments', $groupExpense->group);

        if ($groupExpense->status !== 'draft') {
            return $this->failure('Only a draft expense can be approved.');
        }

        $groupExpense->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

        return $this->saved($groupExpense, 'Expense approved.');
    }

    public function pay(Request $request, GroupExpense $groupExpense, RecordPaymentTransaction $record): JsonResponse
    {
        Gate::authorize('managePayments', $groupExpense->group);

        if ($groupExpense->status !== 'approved') {
            return $this->failure('Only an approved expense can be paid.');
        }

        DB::transaction(function () use ($request, $groupExpense, $record): void {
            $groupExpense->update(['status' => 'paid', 'paid_at' => now()]);
            $record->handle($groupExpense, $request->user());
        });

        return $this->saved($groupExpense, 'Expense marked as paid.');
    }

    private function saved(GroupExpense $expense, string $message, int $status = 200): JsonResponse
    {
        $expense->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new GroupExpenseResource($expense))->resolve()], $status);
    }
}
