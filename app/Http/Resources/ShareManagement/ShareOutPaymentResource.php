<?php

namespace App\Http\Resources\ShareManagement;

use App\Models\ShareManagement\ShareOutPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShareOutPayment
 */
class ShareOutPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array. Expects memberShareOut.groupMember, memberShareOut.shareOut.group and paymentMethod to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shareOut = $this->memberShareOut->shareOut;

        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date->toDateString(),
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'method' => $this->paymentMethod->name,
            'reference' => $this->external_reference,
            'member' => [
                'name' => $this->memberShareOut->groupMember->name,
                'member_number' => $this->memberShareOut->groupMember->member_number,
            ],
            'share_out' => [
                'number' => $shareOut->share_out_number,
                'url' => route('groups.share-outs.show', [$shareOut->group_id, $shareOut->id]),
            ],
            'group' => ['id' => $shareOut->group->id, 'name' => $shareOut->group->name],
        ];
    }
}
