<?php

namespace App\Models\SettingManagement;

use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contribution_frequency_id', 'loan_interest_method_id', 'penalty_type_id',
    'minimum_contribution', 'maximum_contribution', 'share_price', 'minimum_shares', 'maximum_shares',
    'default_loan_interest_rate', 'default_interest_period', 'maximum_loan_multiplier', 'default_loan_duration_months', 'loan_grace_period_days',
    'late_payment_penalty_value', 'loan_guarantors_required', 'withdrawal_approvals_required', 'opening_balance', 'opening_balance_date',
    'allow_partial_contributions', 'allow_multiple_loans',
])]
class GroupSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_contribution' => 'decimal:2',
            'maximum_contribution' => 'decimal:2',
            'share_price' => 'decimal:2',
            'default_loan_interest_rate' => 'decimal:4',
            'maximum_loan_multiplier' => 'decimal:2',
            'late_payment_penalty_value' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'allow_partial_contributions' => 'boolean',
            'allow_multiple_loans' => 'boolean',
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
     * @return BelongsTo<ContributionFrequency, $this>
     */
    public function contributionFrequency(): BelongsTo
    {
        return $this->belongsTo(ContributionFrequency::class);
    }

    /**
     * @return BelongsTo<LoanInterestMethod, $this>
     */
    public function loanInterestMethod(): BelongsTo
    {
        return $this->belongsTo(LoanInterestMethod::class);
    }

    /**
     * @return BelongsTo<PenaltyType, $this>
     */
    public function penaltyType(): BelongsTo
    {
        return $this->belongsTo(PenaltyType::class);
    }
}
