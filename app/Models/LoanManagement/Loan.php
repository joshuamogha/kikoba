<?php

namespace App\Models\LoanManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\SettingManagement\LoanInterestMethod;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'financial_cycle_id', 'loan_application_id', 'group_member_id', 'loan_product_id', 'loan_number', 'principal_amount', 'interest_amount', 'penalty_amount', 'total_payable', 'amount_paid', 'outstanding_balance', 'disbursement_date', 'first_payment_date', 'maturity_date', 'interest_rate', 'loan_interest_method_id', 'status'])]
class Loan extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'disbursement_date' => 'date',
            'first_payment_date' => 'date',
            'maturity_date' => 'date',
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

    /**
     * @return BelongsTo<LoanProduct, $this>
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * @return BelongsTo<LoanInterestMethod, $this>
     */
    public function loanInterestMethod(): BelongsTo
    {
        return $this->belongsTo(LoanInterestMethod::class);
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function loanInstallments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }
}
