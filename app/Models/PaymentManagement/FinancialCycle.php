<?php

namespace App\Models\PaymentManagement;

use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\SettingManagement\ContributionFrequency;
use App\Models\SettingManagement\MemberFine;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\ShareManagement\ShareOut;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Database\Factories\PaymentManagement\FinancialCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['group_id', 'contribution_frequency_id', 'name', 'cycle_number', 'start_date', 'end_date', 'status', 'opened_by', 'closed_by', 'closed_at', 'notes'])]
class FinancialCycle extends Model
{
    /** @use HasFactory<FinancialCycleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<MemberFine, $this>
     */
    public function memberFines(): HasMany
    {
        return $this->hasMany(MemberFine::class);
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
     * @return HasOne<ShareOut, $this>
     */
    public function shareOut(): HasOne
    {
        return $this->hasOne(ShareOut::class);
    }

    /**
     * @return HasMany<MemberShareTransaction, $this>
     */
    public function memberShareTransactions(): HasMany
    {
        return $this->hasMany(MemberShareTransaction::class);
    }
}
