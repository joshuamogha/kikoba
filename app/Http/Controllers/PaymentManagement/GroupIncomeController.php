<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreGroupIncomeRequest;
use App\Http\Resources\PaymentManagement\GroupIncomeResource;
use App\Models\PaymentManagement\GroupIncome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupIncomeController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupFund', 'incomeCategory', 'paymentMethod'];

    /**
     * Show the page, or the group's incomes (with form options for managers) as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.group-incomes', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = $this->canManagePayments($group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->groupIncomes()
            ->with(self::RELATIONS)
            ->when(in_array($status, ['pending', 'approved'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('income_number', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderByDesc('income_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return GroupIncomeResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => $canManage ? [
                'funds' => $this->options($group->groupFunds()->where('status', 'active')->orderBy('name')),
                'categories' => $this->options($group->incomeCategories()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ] : null,
        ])->response();
    }

    public function store(StoreGroupIncomeRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $group = $request->group();
        $date = Carbon::parse($request->validated('income_date'));

        $income = DB::transaction(function () use ($request, $group, $date, $resolveCycle): GroupIncome {
            $income = $group->groupIncomes()->create([
                ...$request->validated(),
                'financial_cycle_id' => $resolveCycle->handle($group, $date, $request->user()->id)->id,
                'income_number' => 'PENDING-'.uniqid(),
                'status' => 'pending',
                'received_by' => $request->user()->id,
            ]);
            $income->update(['income_number' => sprintf('INC-%d-%06d', $date->year, $income->id)]);

            return $income;
        });

        return $this->saved($income, 'Income recorded successfully.', 201);
    }

    public function approve(Request $request, GroupIncome $groupIncome, RecordPaymentTransaction $record): JsonResponse
    {
        Gate::authorize('managePayments', $groupIncome->group);

        if ($groupIncome->status !== 'pending') {
            return $this->failure('Only a pending income can be approved.');
        }

        DB::transaction(function () use ($request, $groupIncome, $record): void {
            $groupIncome->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $record->handle($groupIncome, $request->user());
        });

        return $this->saved($groupIncome, 'Income approved.');
    }

    private function saved(GroupIncome $income, string $message, int $status = 200): JsonResponse
    {
        $income->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new GroupIncomeResource($income))->resolve()], $status);
    }
}
