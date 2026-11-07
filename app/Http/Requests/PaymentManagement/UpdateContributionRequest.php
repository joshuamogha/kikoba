<?php

namespace App\Http\Requests\PaymentManagement;

use App\Models\PaymentManagement\Contribution;
use App\Support\BillingPeriod;
use Illuminate\Validation\Rule;

class UpdateContributionRequest extends GroupScopedRequest
{
    /**
     * The contribution being edited must belong to the group the request names, and only its leaders and Treasurer may edit it.
     */
    public function authorize(): bool
    {
        abort_unless($this->integer('group_id') === $this->contribution()->group_id, 404);

        return parent::authorize();
    }

    /**
     * Same fields as recording a contribution. The member, payment method and funds already on the contribution stay valid
     * even if they have since been deactivated.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $contribution = $this->contribution();

        return [
            'group_member_id' => ['required', 'integer', Rule::exists('group_members', 'id')->where('group_id', $contribution->group_id)
                ->where(fn ($query) => $query->where('membership_status', 'active')->orWhere('id', $contribution->group_member_id))],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('group_id', $contribution->group_id)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $contribution->payment_method_id))],
            'contribution_date' => ['required', 'date', 'before_or_equal:today'],
            'period' => BillingPeriod::rules(),
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.group_fund_id' => ['required', 'integer', 'distinct', Rule::exists('group_funds', 'id')->where('group_id', $contribution->group_id)
                ->where(fn ($query) => $query->where('status', 'active')->orWhereIn('id', $contribution->contributionItems()->pluck('group_fund_id')))],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
        ];
    }

    private function contribution(): Contribution
    {
        return $this->route('contribution');
    }
}
