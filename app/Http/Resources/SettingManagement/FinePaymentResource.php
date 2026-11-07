<?php

namespace App\Http\Resources\SettingManagement;

use App\Models\PaymentManagement\FinePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinePayment
 */
class FinePaymentResource extends JsonResource
{
    /**
     * Expects memberFine.groupMember, memberFine.fineType and paymentMethod to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'fine_number' => $this->memberFine->fine_number,
            'fine_type' => $this->memberFine->fineType->name,
            'member' => ['name' => $this->memberFine->groupMember->name, 'member_number' => $this->memberFine->groupMember->member_number],
            'payment_date' => $this->payment_date->toDateString(),
            'amount' => (float) $this->amount,
            'payment_method' => $this->paymentMethod->name,
            'external_reference' => $this->external_reference,
            'status' => $this->status,
        ];
    }
}
