<?php

namespace App\Http\Resources\UserManagement;

use App\Models\SettingManagement\GroupSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupSetting
 */
class GroupSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array, with the group's active lookup options for the form dropdowns.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $group = $this->group;

        return [
            'id' => $this->id,
            'contribution_frequency_id' => $this->contribution_frequency_id,
            'loan_interest_method_id' => $this->loan_interest_method_id,
            'penalty_type_id' => $this->penalty_type_id,
            'minimum_contribution' => $this->minimum_contribution,
            'maximum_contribution' => $this->maximum_contribution,
            'share_price' => $this->share_price,
            'minimum_shares' => $this->minimum_shares,
            'maximum_shares' => $this->maximum_shares,
            'default_loan_interest_rate' => $this->default_loan_interest_rate,
            'default_interest_period' => $this->default_interest_period,
            'maximum_loan_multiplier' => $this->maximum_loan_multiplier,
            'default_loan_duration_months' => $this->default_loan_duration_months,
            'loan_grace_period_days' => $this->loan_grace_period_days,
            'late_payment_penalty_value' => $this->late_payment_penalty_value,
            'loan_guarantors_required' => $this->loan_guarantors_required,
            'withdrawal_approvals_required' => $this->withdrawal_approvals_required,
            'allow_partial_contributions' => $this->allow_partial_contributions,
            'allow_multiple_loans' => $this->allow_multiple_loans,
            'currency' => $group->currency,
            'options' => [
                'contribution_frequencies' => $this->options($group->contributionFrequencies()),
                'loan_interest_methods' => $this->options($group->loanInterestMethods()),
                'penalty_types' => $this->options($group->penaltyTypes()),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany<*, *>  $relation
     * @return array<int, array{value: int, label: string}>
     */
    private function options($relation): array
    {
        return $relation->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($row) => ['value' => $row->id, 'label' => $row->name])
            ->all();
    }
}
