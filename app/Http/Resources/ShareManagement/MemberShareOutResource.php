<?php

namespace App\Http\Resources\ShareManagement;

use App\Models\ShareManagement\MemberShareOut;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MemberShareOut
 */
class MemberShareOutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member' => [
                'id' => $this->groupMember->id,
                'name' => $this->groupMember->name,
                'member_number' => $this->groupMember->member_number,
                'initials' => $this->groupMember->initials(),
                'is_self' => $this->groupMember->user_id !== null && $this->groupMember->user_id === $request->user()->id,
            ],
            'share_out' => $this->whenLoaded('shareOut', fn () => [
                'number' => $this->shareOut->share_out_number,
                'status' => $this->shareOut->status,
                'url' => route('groups.share-outs.show', [$this->shareOut->group_id, $this->shareOut->id]),
                'cycle' => $this->shareOut->relationLoaded('financialCycle') ? $this->shareOut->financialCycle->name : null,
                'group' => $this->shareOut->relationLoaded('group') ? ['id' => $this->shareOut->group->id, 'name' => $this->shareOut->group->name] : null,
            ]),
            'savings' => (float) $this->total_savings,
            'shares' => $this->number_of_shares,
            'share_value' => (float) $this->share_value,
            'profit' => (float) $this->profit_amount,
            'other_entitlements' => (float) $this->other_entitlements,
            'outstanding_loan' => (float) $this->outstanding_loan,
            'outstanding_fines' => (float) $this->outstanding_fines,
            'other_deductions' => (float) $this->other_deductions,
            'net_payable' => (float) $this->net_payable,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) $this->net_payable - (float) $this->amount_paid,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toDateString(),
            'payments' => $this->whenLoaded('shareOutPayments', fn () => $this->shareOutPayments->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_date' => $payment->payment_date->toDateString(),
                'amount' => (float) $payment->amount,
                'method' => $payment->paymentMethod->name,
                'reference' => $payment->external_reference,
            ])->values()),
        ];
    }
}
