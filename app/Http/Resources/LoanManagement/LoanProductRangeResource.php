<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\LoanProductRange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanProductRange
 */
class LoanProductRangeResource extends JsonResource
{
    /**
     * Expects loanProduct to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_product_id' => $this->loan_product_id,
            'product' => $this->loanProduct->name,
            'repayment_mode' => $this->loanProduct->repayment_mode,
            'minimum_amount' => (float) $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount === null ? null : (float) $this->maximum_amount,
            'duration_months' => $this->duration_months,
        ];
    }
}
