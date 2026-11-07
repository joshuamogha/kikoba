<?php

namespace App\Http\Resources\ShareManagement;

use App\Models\ShareManagement\ShareOut;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShareOut
 */
class ShareOutResource extends JsonResource
{
    /**
     * Transform the resource into an array. Member rows are only present when they were loaded for this viewer.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'share_out_number' => $this->share_out_number,
            'status' => $this->status,
            'calculation_date' => $this->calculation_date->toDateString(),
            'approved_at' => $this->approved_at?->toDateString(),
            'completed_at' => $this->completed_at?->toDateString(),
            'group' => $this->whenLoaded('group', fn () => ['id' => $this->group->id, 'name' => $this->group->name]),
            'url' => route('groups.share-outs.show', [$this->group_id, $this->id]),
            'cycle' => [
                'id' => $this->financialCycle->id,
                'name' => $this->financialCycle->name,
                'start_date' => $this->financialCycle->start_date,
                'end_date' => $this->financialCycle->end_date,
            ],
            'totals' => [
                'savings' => (float) $this->total_savings,
                'share_value' => (float) $this->total_share_value,
                'interest_income' => (float) $this->total_interest_income,
                'other_income' => (float) $this->total_other_income,
                'fine_income' => (float) $this->total_fine_income,
                'expenses' => (float) $this->total_expenses,
                'distributable_profit' => (float) $this->distributable_profit,
                'distributable' => (float) $this->total_distributable,
            ],
            'members_count' => $this->whenCounted('memberShareOuts'),
            'amount_paid' => $this->whenAggregated('memberShareOuts', 'amount_paid', 'sum', fn ($value) => (float) $value),
            'members' => MemberShareOutResource::collection($this->whenLoaded('memberShareOuts')),
        ];
    }
}
