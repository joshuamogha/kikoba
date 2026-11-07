<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanProduct
 */
class LoanProductResource extends JsonResource
{
    /**
     * Expects groupFund, repaymentFrequency and loanInterestMethod to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'group_fund_id' => $this->group_fund_id,
            'fund' => $this->groupFund->name,
            'repayment_frequency_id' => $this->repayment_frequency_id,
            'repayment_frequency' => $this->repaymentFrequency->name,
            'loan_interest_method_id' => $this->loan_interest_method_id,
            'interest_method' => $this->loanInterestMethod->name,
            'minimum_amount' => (float) $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount === null ? null : (float) $this->maximum_amount,
            'maximum_savings_multiplier' => (float) $this->maximum_savings_multiplier,
            'interest_rate' => (float) $this->interest_rate,
            'interest_period' => $this->interest_period,
            'repayment_mode' => $this->repayment_mode,
            'ranges' => $this->whenLoaded('loanProductRanges', fn () => $this->loanProductRanges->map(fn ($range) => [
                'minimum_amount' => (float) $range->minimum_amount,
                'maximum_amount' => $range->maximum_amount === null ? null : (float) $range->maximum_amount,
                'duration_months' => $range->duration_months,
            ])->all(), []),
            'minimum_duration_months' => $this->minimum_duration_months,
            'maximum_duration_months' => $this->maximum_duration_months,
            'grace_period_days' => $this->grace_period_days,
            'guarantors_required' => $this->guarantors_required,
            'status' => $this->status,
        ];
    }
}
