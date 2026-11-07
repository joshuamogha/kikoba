<?php

namespace App\Models\ShareManagement;

use App\Models\UserManagement\GroupMember;
use Database\Factories\ShareManagement\MemberShareOutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['share_out_id', 'group_member_id', 'total_savings', 'number_of_shares', 'share_value', 'profit_amount', 'other_entitlements', 'outstanding_loan', 'outstanding_fines', 'other_deductions', 'net_payable', 'amount_paid', 'status', 'paid_at'])]
class MemberShareOut extends Model
{
    /** @use HasFactory<MemberShareOutFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const PARTIAL = 'partial';

    public const PAID = 'paid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'total_savings' => 'decimal:2',
            'share_value' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'other_entitlements' => 'decimal:2',
            'outstanding_loan' => 'decimal:2',
            'outstanding_fines' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ShareOut, $this>
     */
    public function shareOut(): BelongsTo
    {
        return $this->belongsTo(ShareOut::class);
    }

    /**
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    /**
     * @return HasMany<ShareOutPayment, $this>
     */
    public function shareOutPayments(): HasMany
    {
        return $this->hasMany(ShareOutPayment::class);
    }
}
