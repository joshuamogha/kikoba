<?php

namespace App\Models\SettingManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\PaymentManagement\FinePayment;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'financial_cycle_id', 'group_member_id', 'fine_type_id', 'fine_number', 'reason', 'fine_date', 'due_date', 'amount', 'amount_paid', 'balance', 'status', 'issued_by', 'approved_by', 'waiver_reason'])]
class MemberFine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fine_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
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
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    /**
     * @return BelongsTo<FineType, $this>
     */
    public function fineType(): BelongsTo
    {
        return $this->belongsTo(FineType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<FinePayment, $this>
     */
    public function finePayments(): HasMany
    {
        return $this->hasMany(FinePayment::class);
    }
}
