<?php

namespace App\Http\Requests\PaymentManagement;

use App\Support\BillingPeriod;

class StoreManyContributionsRequest extends GroupScopedRequest
{
    /**
     * One date, month and payment method for the whole batch; each row is one member with the amount per fund.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'integer', $this->inGroup('payment_methods')],
            'contribution_date' => ['required', 'date', 'before_or_equal:today'],
            'period' => BillingPeriod::rules(),
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.group_member_id' => ['required', 'integer', 'distinct', $this->inGroup('group_members', 'membership_status', 'active')],
            'rows.*.external_reference' => ['nullable', 'string', 'max:255'],
            'rows.*.items' => ['required', 'array', 'min:1'],
            'rows.*.items.*.group_fund_id' => ['required', 'integer', $this->inGroup('group_funds', 'status', 'active')],
            'rows.*.items.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
        ];
    }
}
