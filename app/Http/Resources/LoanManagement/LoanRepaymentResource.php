<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\LoanRepayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanRepayment
 */
class LoanRepaymentResource extends JsonResource
{
    /**
     * Includes the loan number, member and method when those relations are loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repayment_number' => $this->repayment_number,
            'loan_number' => $this->whenLoaded('loan', fn () => $this->loan->loan_number),
            'member' => $this->whenLoaded('groupMember', fn () => $this->groupMember->name),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod->name),
            'payment_date' => $this->payment_date->toDateString(),
            'amount' => (float) $this->amount,
            'principal_amount' => (float) $this->principal_amount,
            'interest_amount' => (float) $this->interest_amount,
            'penalty_amount' => (float) $this->penalty_amount,
            'status' => $this->status,
        ];
    }
}
