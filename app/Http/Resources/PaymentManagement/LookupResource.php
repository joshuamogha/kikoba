<?php

namespace App\Http\Resources\PaymentManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method, income category or expense category row.
 */
class LookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code ?? null,
            'interval_days' => $this->interval_days ?? null,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
