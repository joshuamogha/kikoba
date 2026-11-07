<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\LoanInstallment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanInstallment
 */
class LoanInstallmentResource extends JsonResource
{
    /**
     * Includes the loan number and member when the loan relation is loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_number' => $this->whenLoaded('loan', fn () => $this->loan->loan_number),
            'member' => $this->whenLoaded('loan', fn () => $this->loan->groupMember->name),
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date->toDateString(),
            'principal_due' => (float) $this->principal_due,
            'interest_due' => (float) $this->interest_due,
            'penalty_due' => (float) $this->penalty_due,
            'total_due' => (float) $this->total_due,
            'total_paid' => (float) $this->total_paid,
            'status' => $this->status,
        ];
    }
}
