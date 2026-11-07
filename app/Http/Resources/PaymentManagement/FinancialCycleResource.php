<?php

namespace App\Http\Resources\PaymentManagement;

use App\Models\PaymentManagement\FinancialCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialCycle
 */
class FinancialCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cycle_number' => $this->cycle_number,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status,
            'contribution_frequency_id' => $this->contribution_frequency_id,
            'frequency' => $this->contributionFrequency?->name,
        ];
    }
}
