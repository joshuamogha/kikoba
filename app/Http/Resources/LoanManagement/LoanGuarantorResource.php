<?php

namespace App\Http\Resources\LoanManagement;

use App\Models\LoanManagement\LoanGuarantor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanGuarantor
 */
class LoanGuarantorResource extends JsonResource
{
    /**
     * Expects groupMember and loanApplication.groupMember to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->loanApplication->application_number,
            'application_status' => $this->loanApplication->status,
            'borrower' => $this->loanApplication->groupMember->name,
            'requested_amount' => (float) $this->loanApplication->requested_amount,
            'guarantor' => ['name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'guaranteed_amount' => (float) $this->guaranteed_amount,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'responded_at' => $this->responded_at?->toDateTimeString(),
            'is_mine' => $this->groupMember->user_id === $request->user()?->id,
        ];
    }
}
