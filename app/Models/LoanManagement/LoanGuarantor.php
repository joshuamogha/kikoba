<?php

namespace App\Models\LoanManagement;

use App\Models\UserManagement\GroupMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_application_id', 'group_member_id', 'guaranteed_amount', 'status', 'responded_at', 'remarks'])]
class LoanGuarantor extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guaranteed_amount' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LoanApplication, $this>
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }
}
