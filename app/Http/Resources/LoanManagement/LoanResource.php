<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
{
    /**
     * Expects groupMember and loanProduct to be loaded; installments and repayments are included when loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_number' => $this->loan_number,
            'member' => ['name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'product' => $this->loanProduct->name,
            'principal_amount' => (float) $this->principal_amount,
            'interest_amount' => (float) $this->interest_amount,
            'penalty_amount' => (float) $this->penalty_amount,
            'total_payable' => (float) $this->total_payable,
            'amount_paid' => (float) $this->amount_paid,
            'outstanding_balance' => (float) $this->outstanding_balance,
            'disbursement_date' => $this->disbursement_date->toDateString(),
            'maturity_date' => $this->maturity_date->toDateString(),
            'interest_rate' => (float) $this->interest_rate,
            'status' => $this->status,
            'installments' => $this->whenLoaded('loanInstallments', fn () => LoanInstallmentResource::collection($this->loanInstallments)->resolve()),
            'repayments' => $this->whenLoaded('loanRepayments', fn () => LoanRepaymentResource::collection($this->loanRepayments)->resolve()),
        ];
    }
}
