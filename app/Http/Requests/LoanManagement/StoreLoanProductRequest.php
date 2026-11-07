<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use Illuminate\Validation\Rule;

class StoreLoanProductRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $this->uniqueCode()],
            'description' => ['nullable', 'string', 'max:500'],
            'group_fund_id' => ['required', 'integer', $this->inGroup('group_funds', 'status', 'active')],
            'repayment_frequency_id' => ['required', 'integer', $this->inGroup('repayment_frequencies')],
            'loan_interest_method_id' => ['required', 'integer', $this->inGroup('loan_interest_methods')],
            'minimum_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'maximum_amount' => ['nullable', 'numeric', 'gte:minimum_amount', 'max:9999999999999999'],
            'maximum_savings_multiplier' => ['required', 'numeric', 'min:0', 'max:1000'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_period' => ['required', Rule::in(['per_loan', 'per_month', 'per_year'])],
            'repayment_mode' => ['sometimes', Rule::in(['installments', 'lump_sum'])],
            'minimum_duration_months' => ['required', 'integer', 'min:1', 'max:600'],
            'maximum_duration_months' => ['required', 'integer', 'gte:minimum_duration_months', 'max:600'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'guarantors_required' => ['required', 'integer', 'min:0', 'max:50'],
        ];
    }

    protected function uniqueCode(): mixed
    {
        return Rule::unique('loan_products', 'code')->where('group_id', $this->group()->id);
    }
}
