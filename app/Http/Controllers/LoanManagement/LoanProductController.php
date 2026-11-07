<?php

namespace App\Http\Controllers\LoanManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\StoreLoanProductRequest;
use App\Http\Requests\LoanManagement\UpdateLoanProductRequest;
use App\Http\Resources\LoanManagement\LoanProductResource;
use App\Models\LoanManagement\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupFund:id,name', 'repaymentFrequency:id,name', 'loanInterestMethod:id,name', 'loanProductRanges'];

    /**
     * Show the page, or the group's loan products with form options and the group's loan defaults as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-products', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $setting = $group->ensureGroupSetting();

        $products = $group->loanProducts()
            ->with(self::RELATIONS)
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return LoanProductResource::collection($products)->additional([
            'can_manage' => Gate::allows('manageLoans', $group),
            'options' => [
                'funds' => $this->options($group->groupFunds()->where('status', 'active')->orderBy('name')),
                'repayment_frequencies' => $this->options($group->repaymentFrequencies()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'interest_methods' => $this->options($group->loanInterestMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'defaults' => [
                    'interest_rate' => (float) $setting->default_loan_interest_rate,
                    'maximum_savings_multiplier' => (float) $setting->maximum_loan_multiplier,
                    'maximum_duration_months' => $setting->default_loan_duration_months,
                    'grace_period_days' => $setting->loan_grace_period_days,
                    'guarantors_required' => $setting->loan_guarantors_required,
                ],
            ],
        ])->response();
    }

    public function store(StoreLoanProductRequest $request): JsonResponse
    {
        $product = $request->group()->loanProducts()->create([
            ...$request->validated(),
            'code' => Str::upper($request->validated('code')),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return $this->saved($product, 'Loan product created successfully.', 201);
    }

    public function update(UpdateLoanProductRequest $request, LoanProduct $loanProduct): JsonResponse
    {
        abort_unless($loanProduct->group_id === $request->group()->id, 404);

        $loanProduct->update([...$request->validated(), 'code' => Str::upper($request->validated('code'))]);

        return $this->saved($loanProduct, 'Loan product updated successfully.');
    }

    private function saved(LoanProduct $product, string $message, int $status = 200): JsonResponse
    {
        $product->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new LoanProductResource($product))->resolve()], $status);
    }
}
