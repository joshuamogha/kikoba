<?php

namespace App\Http\Resources\PaymentManagement;

use App\Models\PaymentManagement\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentTransaction
 */
class PaymentTransactionResource extends JsonResource
{
    /**
     * Expects paymentMethod and groupMember to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'type' => $this->transaction_type,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider_transaction_id' => $this->provider_transaction_id,
            'payment_method' => $this->paymentMethod->name,
            'member' => $this->groupMember?->name,
            'date' => ($this->completed_at ?? $this->initiated_at ?? $this->created_at)->toDateString(),
        ];
    }
}
