<?php

namespace App\Http\Controllers\LoanManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\StoreLoanProductRangeRequest;
use App\Http\Requests\LoanManagement\UpdateLoanProductRangeRequest;
use App\Http\Resources\LoanManagement\LoanProductRangeResource;
use App\Models\LoanManagement\LoanProductRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanProductRangeController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the page, or the group's loan amount ranges (all products, or one with product_id) as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-ranges', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $search = trim($request->string('q')->toString());

        $ranges = LoanProductRange::query()
            ->with('loanProduct')
            ->whereHas('loanProduct', fn ($product) => $product->where('group_id', $group->id)
                ->when($request->integer('product_id'), fn ($query, $id) => $query->whereKey($id))
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%")))
            ->join('loan_products', 'loan_products.id', '=', 'loan_product_ranges.loan_product_id')
            ->orderBy('loan_products.name')
            ->orderBy('loan_product_ranges.minimum_amount')
            ->select('loan_product_ranges.*')
            ->paginate(15)
            ->withQueryString();

        return LoanProductRangeResource::collection($ranges)->additional([
            'can_manage' => Gate::allows('manageLoans', $group),
            'options' => [
                'products' => $group->loanProducts()->orderBy('name')->get(['id', 'name', 'repayment_mode'])
                    ->map(fn ($product) => ['value' => $product->id, 'label' => $product->name, 'repayment_mode' => $product->repayment_mode])->all(),
            ],
        ])->response();
    }

    public function store(StoreLoanProductRangeRequest $request): JsonResponse
    {
        $range = LoanProductRange::query()->create($request->validated());
        $range->loanProduct->syncDurationsFromRanges();

        return $this->saved($range, 'Loan range added.', 201);
    }

    public function update(UpdateLoanProductRangeRequest $request, LoanProductRange $loanRange): JsonResponse
    {
        abort_unless($loanRange->loanProduct->group_id === $request->group()->id, 404);

        $loanRange->update($request->safe()->except('loan_product_id'));
        $loanRange->loanProduct->syncDurationsFromRanges();

        return $this->saved($loanRange, 'Loan range updated.');
    }

    /**
     * Remove a range. Loans already given keep the term they were given with.
     */
    public function destroy(LoanProductRange $loanRange): JsonResponse
    {
        Gate::authorize('manageLoans', $loanRange->loanProduct->group);

        $product = $loanRange->loanProduct;
        $loanRange->delete();
        $product->syncDurationsFromRanges();

        return response()->json(['success' => true, 'message' => 'Loan range removed.']);
    }

    private function saved(LoanProductRange $range, string $message, int $status = 200): JsonResponse
    {
        $range->load('loanProduct');

        return response()->json(['success' => true, 'message' => $message, 'data' => (new LoanProductRangeResource($range))->resolve()], $status);
    }
}
