<?php

namespace App\Http\Resources\PaymentManagement;

use App\Models\PaymentManagement\GroupExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupExpense
 */
class GroupExpenseResource extends JsonResource
{
    /**
     * Expects groupFund, groupExpenseCategory and paymentMethod to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'expense_date' => $this->expense_date->toDateString(),
            'description' => $this->description,
            'payee_name' => $this->payee_name,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'fund' => $this->groupFund->name,
            'category' => $this->groupExpenseCategory->name,
            'payment_method' => $this->paymentMethod->name,
        ];
    }
}
