<?php

namespace App\Http\Requests\PaymentManagement;

use App\Support\BillingPeriod;

class StoreContributionRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_member_id' => ['required', 'integer', $this->inGroup('group_members', 'membership_status', 'active')],
            'payment_method_id' => ['required', 'integer', $this->inGroup('payment_methods')],
            'contribution_date' => ['required', 'date', 'before_or_equal:today'],
            'period' => BillingPeriod::rules(),
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.group_fund_id' => ['required', 'integer', 'distinct', $this->inGroup('group_funds', 'status', 'active')],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
        ];
    }
}
