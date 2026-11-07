<?php

namespace App\Models\ShareManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Database\Factories\ShareManagement\ShareOutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'financial_cycle_id', 'share_out_number', 'calculation_date', 'total_savings', 'total_share_value', 'total_interest_income', 'total_other_income', 'total_fine_income', 'total_expenses', 'distributable_profit', 'total_distributable', 'status', 'calculated_by', 'approved_by', 'approved_at', 'completed_at'])]
class ShareOut extends Model
{
    /** @use HasFactory<ShareOutFactory> */
    use HasFactory;

    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const COMPLETED = 'completed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation_date' => 'date',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_savings' => 'decimal:2',
            'total_share_value' => 'decimal:2',
            'total_interest_income' => 'decimal:2',
            'total_other_income' => 'decimal:2',
            'total_fine_income' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'distributable_profit' => 'decimal:2',
            'total_distributable' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<FinancialCycle, $this>
     */
    public function financialCycle(): BelongsTo
    {
        return $this->belongsTo(FinancialCycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<MemberShareOut, $this>
     */
    public function memberShareOuts(): HasMany
    {
        return $this->hasMany(MemberShareOut::class);
    }

    /**
     * Mark the share out completed once every member row is paid.
     */
    public function completeWhenAllPaid(): void
    {
        if ($this->status === self::APPROVED && ! $this->memberShareOuts()->where('status', '!=', MemberShareOut::PAID)->exists()) {
            $this->update(['status' => self::COMPLETED, 'completed_at' => now()]);
        }
    }
}
