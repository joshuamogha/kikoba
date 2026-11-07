<?php

namespace App\Http\Resources\PaymentManagement;

use App\Models\PaymentManagement\GroupFund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupFund
 */
class GroupFundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'fund_type_id' => $this->fund_type_id,
            'fund_type' => $this->fundType?->name,
            'contribution_frequency_id' => $this->contribution_frequency_id,
            'frequency' => $this->contributionFrequency?->name,
            'minimum_contribution' => (float) $this->minimum_contribution,
            'is_mandatory' => $this->is_mandatory,
            'is_distributable' => $this->is_distributable,
            'is_loanable' => $this->is_loanable,
            'status' => $this->status,
        ];
    }
}
