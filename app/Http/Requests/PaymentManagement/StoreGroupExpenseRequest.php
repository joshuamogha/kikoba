<?php

namespace App\Http\Requests\PaymentManagement;

class StoreGroupExpenseRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_fund_id' => ['required', 'integer', $this->inGroup('group_funds', 'status', 'active')],
            'group_expense_category_id' => ['required', 'integer', $this->inGroup('group_expense_categories')],
            'payment_method_id' => ['required', 'integer', $this->inGroup('payment_methods')],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'payee_name' => ['required', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
