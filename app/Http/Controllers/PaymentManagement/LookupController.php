<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreLookupRequest;
use App\Http\Requests\PaymentManagement\UpdateLookupRequest;
use App\Http\Resources\PaymentManagement\LookupResource;
use App\Models\LoanManagement\LoanType;
use App\Models\PaymentManagement\GroupExpenseCategory;
use App\Models\PaymentManagement\IncomeCategory;
use App\Models\PaymentManagement\PaymentMethod;
use App\Models\PaymentManagement\RepaymentFrequency;
use App\Models\SettingManagement\ContributionFrequency;
use App\Models\SettingManagement\FundType;
use App\Models\SettingManagement\LoanInterestMethod;
use App\Models\SettingManagement\MembershipType;
use App\Models\SettingManagement\PenaltyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One controller for the three per-group lookups: payment methods, income categories and expense categories.
 * The route default "lookup" says which one a request is for.
 */
class LookupController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * @var array<string, array{model: class-string<Model>, title: string, table: string, code: bool, lock_code?: bool, interval?: bool}>
     */
    private const LOOKUPS = [
        'payment-methods' => ['model' => PaymentMethod::class, 'title' => 'Payment methods', 'table' => 'payment_methods', 'code' => true],
        'income-categories' => ['model' => IncomeCategory::class, 'title' => 'Income categories', 'table' => 'income_categories', 'code' => false],
        'fund-types' => ['model' => FundType::class, 'title' => 'Fund types', 'table' => 'fund_types', 'code' => true],
        'contribution-frequencies' => ['model' => ContributionFrequency::class, 'title' => 'Contribution frequencies', 'table' => 'contribution_frequencies', 'code' => false],
        'loan-interest-methods' => ['model' => LoanInterestMethod::class, 'title' => 'Loan interest methods', 'table' => 'loan_interest_methods', 'code' => true, 'lock_code' => true],
        'membership-types' => ['model' => MembershipType::class, 'title' => 'Membership types', 'table' => 'membership_types', 'code' => false],
        'penalty-types' => ['model' => PenaltyType::class, 'title' => 'Penalty types', 'table' => 'penalty_types', 'code' => false],
        'repayment-frequencies' => ['model' => RepaymentFrequency::class, 'title' => 'Repayment frequencies', 'table' => 'repayment_frequencies', 'code' => false, 'interval' => true],
        'loan-types' => ['model' => LoanType::class, 'title' => 'Loan types', 'table' => 'loan_types', 'code' => false],
        'group-expense-categories' => ['model' => GroupExpenseCategory::class, 'title' => 'Expense categories', 'table' => 'group_expense_categories', 'code' => false],
    ];

    /**
     * @return array{model: class-string<Model>, title: string, table: string, code: bool, lock_code?: bool, interval?: bool}
     */
    public static function definition(string $slug): array
    {
        return self::LOOKUPS[$slug];
    }

    /**
     * Show the page, or the group's rows as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        $lookup = self::definition($request->route()->defaults['lookup']);

        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.lookup', [
                'groups' => $this->groupOptions($request->user()),
                'title' => $lookup['title'],
                'endpoint' => '/'.$request->route()->defaults['lookup'],
                'hasCode' => $lookup['code'],
                'hasInterval' => $lookup['interval'] ?? false,
            ]);
        }

        $group = $this->groupFor($request);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $lookup['model']::query()
            ->where('group_id', $group->id)
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return LookupResource::collection($rows)->additional(['can_manage' => $this->canManagePayments($group)])->response();
    }

    public function store(StoreLookupRequest $request): JsonResponse
    {
        $lookup = self::definition($request->route()->defaults['lookup']);
        $group = $request->group();

        $row = $lookup['model']::query()->create([
            ...$request->validated(),
            'group_id' => $group->id,
            ...($lookup['code'] ? ['code' => Str::upper($request->validated('code'))] : []),
            'display_order' => ($lookup['model']::query()->where('group_id', $group->id)->max('display_order') ?? 0) + 1,
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'message' => Str::singular($lookup['title']).' created successfully.', 'data' => (new LookupResource($row))->resolve()], 201);
    }

    public function update(UpdateLookupRequest $request, int $record): JsonResponse
    {
        $lookup = self::definition($request->route()->defaults['lookup']);
        $row = $lookup['model']::query()->where('group_id', $request->group()->id)->findOrFail($record);

        $row->update([
            ...$request->safe()->except('code'),
            ...($lookup['code'] && ! ($lookup['lock_code'] ?? false) ? ['code' => Str::upper($request->validated('code'))] : []),
        ]);

        return response()->json(['success' => true, 'message' => Str::singular($lookup['title']).' updated successfully.', 'data' => (new LookupResource($row))->resolve()]);
    }
}
