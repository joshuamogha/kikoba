<?php

namespace App\Http\Resources\PaymentManagement;

use App\Models\PaymentManagement\Contribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contribution
 */
class ContributionResource extends JsonResource
{
    /**
     * Expects groupMember, paymentMethod and contributionItems.groupFund to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contribution_number' => $this->contribution_number,
            'contribution_date' => $this->contribution_date->toDateString(),
            'period' => ($this->contributionItems->first()?->period_start ?? $this->contribution_date)->format('Y-m'),
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'external_reference' => $this->external_reference,
            'member' => ['name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'payment_method' => $this->paymentMethod->name,
            'funds' => $this->contributionItems->map(fn ($item) => ['name' => $item->groupFund->name, 'amount' => (float) $item->amount])->all(),
        ];
    }
}
