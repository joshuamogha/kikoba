<?php

namespace App\Models\LoanManagement;

use App\Models\PaymentManagement\GroupFund;
use App\Models\PaymentManagement\RepaymentFrequency;
use App\Models\SettingManagement\LoanInterestMethod;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['group_id', 'group_fund_id', 'repayment_frequency_id', 'name', 'code', 'description', 'minimum_amount', 'maximum_amount', 'maximum_savings_multiplier', 'loan_interest_method_id', 'interest_rate', 'interest_period', 'repayment_mode', 'minimum_duration_months', 'maximum_duration_months', 'grace_period_days', 'guarantors_required', 'status', 'created_by'])]
class LoanProduct extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'maximum_savings_multiplier' => 'decimal:2',
            'interest_rate' => 'decimal:4',
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
     * @return BelongsTo<GroupFund, $this>
     */
    public function groupFund(): BelongsTo
    {
        return $this->belongsTo(GroupFund::class);
    }

    /**
     * @return BelongsTo<RepaymentFrequency, $this>
     */
    public function repaymentFrequency(): BelongsTo
    {
        return $this->belongsTo(RepaymentFrequency::class);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * The amount ranges that decide the loan term, lowest first.
     *
     * @return HasMany<LoanProductRange, $this>
     */
    public function loanProductRanges(): HasMany
    {
        return $this->hasMany(LoanProductRange::class)->orderBy('minimum_amount');
    }

    /**
     * Keep the product's shortest and longest term in line with its ranges (unchanged when it has none).
     */
    public function syncDurationsFromRanges(): void
    {
        $months = $this->loanProductRanges()->pluck('duration_months');

        if ($months->isNotEmpty()) {
            $this->update(['minimum_duration_months' => $months->min(), 'maximum_duration_months' => $months->max()]);
        }
    }

    /**
     * Whether the loan term is decided by the amount ranges.
     */
    public function hasRanges(): bool
    {
        return $this->loanProductRanges()->exists();
    }

    /**
     * The term in months of the range that covers the amount, or null when no range does.
     */
    public function termFor(float $amount): ?int
    {
        $range = $this->loanProductRanges()
            ->where('minimum_amount', '<=', $amount)
            ->where(fn ($query) => $query->whereNull('maximum_amount')->orWhere('maximum_amount', '>=', $amount))
            ->first();

        return $range?->duration_months;
    }

    /**
     * The date the last payment of a loan of this term falls due when it is given on the date: the single due date for one payment at the
     * end, otherwise the last installment of the repayment frequency.
     */
    public function endDateFor(int $months, Carbon $disbursement): Carbon
    {
        if ($this->repayment_mode === 'lump_sum') {
            return $disbursement->copy()->addMonthsNoOverflow($months)->addDays($this->grace_period_days);
        }

        $interval = max(1, $this->repaymentFrequency->interval_days);
        $count = max(1, (int) round($months * 30 / $interval));

        return $disbursement->copy()->addDays($this->grace_period_days + $interval * $count);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
