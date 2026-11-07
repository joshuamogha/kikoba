<?php

namespace App\Models\PaymentManagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contribution_id', 'group_fund_id', 'period_start', 'period_end', 'amount', 'notes'])]
class ContributionItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Contribution, $this>
     */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    /**
     * @return BelongsTo<GroupFund, $this>
     */
    public function groupFund(): BelongsTo
    {
        return $this->belongsTo(GroupFund::class);
    }
}
