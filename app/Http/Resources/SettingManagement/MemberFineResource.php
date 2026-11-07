<?php

namespace App\Http\Resources\SettingManagement;

use App\Models\SettingManagement\MemberFine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MemberFine
 */
class MemberFineResource extends JsonResource
{
    /**
     * Expects groupMember and fineType to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fine_number' => $this->fine_number,
            'member' => ['name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'fine_type' => $this->fineType->name,
            'reason' => $this->reason,
            'fine_date' => $this->fine_date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'amount' => (float) $this->amount,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) $this->balance,
            'status' => $this->status,
            'waiver_reason' => $this->waiver_reason,
        ];
    }
}
