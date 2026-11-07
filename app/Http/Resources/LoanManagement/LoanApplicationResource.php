<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanApplication
 */
class LoanApplicationResource extends JsonResource
{
    /**
     * The loan's maturity date once it is given; before that the date it would end if given today. None for rejected or cancelled ones.
     */
    private function endDate(): ?string
    {
        if ($this->loan !== null) {
            return $this->loan->maturity_date->toDateString();
        }

        if (! in_array($this->status, ['submitted', 'approved'], true)) {
            return null;
        }

        return $this->loanProduct->endDateFor($this->duration_months, today())->toDateString();
    }

    /**
     * Expects groupMember, loanProduct (with its repaymentFrequency), loanGuarantors and loan to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'member' => ['name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'product' => $this->loanProduct->name,
            'requested_amount' => (float) $this->requested_amount,
            'approved_amount' => $this->approved_amount === null ? null : (float) $this->approved_amount,
            'purpose' => $this->purpose,
            'duration_months' => $this->duration_months,
            'end_date' => $this->endDate(),
            'end_date_is_expected' => $this->loan === null,
            'interest_rate' => (float) $this->interest_rate,
            'application_date' => $this->application_date->toDateString(),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'guarantors_accepted' => $this->loanGuarantors->where('status', 'accepted')->count(),
            'guarantors_required' => $this->loanProduct->guarantors_required,
            'is_own' => $this->groupMember->user_id === $request->user()?->id,
        ];
    }
}
