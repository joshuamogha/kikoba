<?php

namespace App\Models\PaymentManagement;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['group_id', 'financial_cycle_id', 'group_fund_id', 'income_number', 'income_category_id', 'income_date', 'description', 'amount', 'payment_method_id', 'external_reference', 'status', 'received_by', 'approved_by', 'approved_at'])]
class GroupIncome extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
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
     * @return BelongsTo<GroupFund, $this>
     */
    public function groupFund(): BelongsTo
    {
        return $this->belongsTo(GroupFund::class);
    }

    /**
     * @return BelongsTo<IncomeCategory, $this>
     */
    public function incomeCategory(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return MorphMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'reference');
    }
}
