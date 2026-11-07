<?php

namespace App\Models\LoanManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\SettingManagement\LoanInterestMethod;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['group_id', 'financial_cycle_id', 'loan_product_id', 'group_member_id', 'application_number', 'requested_amount', 'approved_amount', 'purpose', 'duration_months', 'interest_rate', 'loan_interest_method_id', 'application_date', 'status', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'rejection_reason'])]
class LoanApplication extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'application_date' => 'date',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<LoanProduct, $this>
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    /**
     * @return BelongsTo<LoanInterestMethod, $this>
     */
    public function loanInterestMethod(): BelongsTo
    {
        return $this->belongsTo(LoanInterestMethod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<LoanGuarantor, $this>
     */
    public function loanGuarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    /**
     * @return HasOne<Loan, $this>
     */
    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }
}
