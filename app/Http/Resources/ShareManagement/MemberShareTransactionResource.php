<?php

namespace App\Http\Resources\ShareManagement;

use App\Models\ShareManagement\MemberShareTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MemberShareTransaction
 */
class MemberShareTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array. Expects groupMember, group and financialCycle to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'type' => $this->transaction_type,
            'shares' => $this->number_of_shares,
            'price_per_share' => (float) $this->price_per_share,
            'total_amount' => (float) $this->total_amount,
            'transaction_date' => $this->transaction_date->toDateString(),
            'period' => ($this->period_start ?? $this->transaction_date)->format('Y-m'),
            'status' => $this->status,
            'reference' => $this->reference,
            'cycle' => $this->financialCycle->name,
            'member' => ['name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'group' => ['id' => $this->group->id, 'name' => $this->group->name],
        ];
    }
}
