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
     * Expects the relations listed in ContributionController::RELATIONS to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = (bool) $request->attributes->get('can_manage', false);
        $shareLinked = $this->shareTransactions->isNotEmpty();
        $transaction = $this->paymentTransactions->first();

        return [
            'id' => $this->id,
            'contribution_number' => $this->contribution_number,
            'contribution_date' => $this->contribution_date->toDateString(),
            'period' => ($this->contributionItems->first()?->period_start ?? $this->contribution_date)->format('Y-m'),
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'external_reference' => $this->external_reference,
            'notes' => $this->notes,
            'member' => ['id' => $this->groupMember->id, 'name' => $this->groupMember->name, 'member_number' => $this->groupMember->member_number],
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => $this->paymentMethod->name,
            'cycle' => $this->financialCycle?->name,
            'received_by' => $this->receiver?->name,
            'approved_by' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'payment_transaction' => $transaction?->transaction_number,
            'share_linked' => $shareLinked,
            'share_purchases' => $this->shareTransactions->map(fn ($purchase) => ['number' => $purchase->transaction_number, 'shares' => $purchase->number_of_shares])->all(),
            'editable' => $canManage && ! $shareLinked,
            'funds' => $this->contributionItems->map(fn ($item) => ['id' => $item->group_fund_id, 'name' => $item->groupFund->name, 'amount' => (float) $item->amount])->all(),
        ];
    }
}
