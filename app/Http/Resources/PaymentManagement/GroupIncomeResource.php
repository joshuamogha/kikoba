<?php

namespace App\Http\Resources\PaymentManagement;

use App\Models\PaymentManagement\GroupIncome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupIncome
 */
class GroupIncomeResource extends JsonResource
{
    /**
     * Expects groupFund, incomeCategory and paymentMethod to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'income_number' => $this->income_number,
            'income_date' => $this->income_date->toDateString(),
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'fund' => $this->groupFund->name,
            'category' => $this->incomeCategory->name,
            'payment_method' => $this->paymentMethod->name,
        ];
    }
}
