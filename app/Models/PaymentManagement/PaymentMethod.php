<?php

namespace App\Models\PaymentManagement;

use App\Models\LoanManagement\LoanRepayment;
use App\Models\ShareManagement\ShareOutPayment;
use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'code', 'description', 'display_order', 'is_active'])]
class PaymentMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /**
     * @return HasMany<GroupIncome, $this>
     */
    public function groupIncomes(): HasMany
    {
        return $this->hasMany(GroupIncome::class);
    }

    /**
     * @return HasMany<GroupExpense, $this>
     */
    public function groupExpenses(): HasMany
    {
        return $this->hasMany(GroupExpense::class);
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return HasMany<ShareOutPayment, $this>
     */
    public function shareOutPayments(): HasMany
    {
        return $this->hasMany(ShareOutPayment::class);
    }

    /**
     * @return HasMany<FinePayment, $this>
     */
    public function finePayments(): HasMany
    {
        return $this->hasMany(FinePayment::class);
    }
}
