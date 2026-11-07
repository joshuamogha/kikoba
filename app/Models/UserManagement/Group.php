<?php

namespace App\Models\UserManagement;

use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\LoanManagement\LoanProduct;
use App\Models\LoanManagement\LoanType;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\FinancialCycle;
use App\Models\PaymentManagement\GroupExpense;
use App\Models\PaymentManagement\GroupExpenseCategory;
use App\Models\PaymentManagement\GroupFund;
use App\Models\PaymentManagement\GroupIncome;
use App\Models\PaymentManagement\IncomeCategory;
use App\Models\PaymentManagement\PaymentMethod;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\PaymentManagement\RepaymentFrequency;
use App\Models\SettingManagement\ContributionFrequency;
use App\Models\SettingManagement\FineType;
use App\Models\SettingManagement\FundType;
use App\Models\SettingManagement\GroupSetting;
use App\Models\SettingManagement\LoanInterestMethod;
use App\Models\SettingManagement\MemberFine;
use App\Models\SettingManagement\MembershipType;
use App\Models\SettingManagement\PenaltyType;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\ShareManagement\ShareOut;
use Database\Factories\UserManagement\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['group_type_id', 'name', 'description', 'registration_number', 'established_date', 'currency', 'maximum_members', 'status', 'created_by'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * Next free member number, e.g. KND-013. Reuses the prefix of existing numbers, otherwise the group name's letters.
     */
    public function nextMemberNumber(): string
    {
        $latest = $this->groupMembers()->orderByDesc('id')->value('member_number');

        $prefix = $latest !== null && str_contains($latest, '-')
            ? explode('-', $latest)[0]
            : (Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $this->name) ?? '', 0, 3)) ?: 'GRP');

        $sequence = $this->groupMembers()->count() + 1;

        while ($this->groupMembers()->where('member_number', sprintf('%s-%03d', $prefix, $sequence))->exists()) {
            $sequence++;
        }

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    /**
     * Active holders of a system role (e.g. Chairperson) among this group's active members.
     *
     * @return Builder<GroupMemberRole>
     */
    public function activeRoleAssignments(string $systemRoleName): Builder
    {
        return GroupMemberRole::query()
            ->active()
            ->whereHas('groupRole', fn ($query) => $query->whereNull('group_id')->where('name', $systemRoleName))
            ->whereHas('groupMember', fn ($query) => $query->where('group_id', $this->id)->where('membership_status', 'active'));
    }

    /**
     * @return BelongsTo<GroupType, $this>
     */
    public function groupType(): BelongsTo
    {
        return $this->belongsTo(GroupType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ContributionFrequency, $this>
     */
    public function contributionFrequencies(): HasMany
    {
        return $this->hasMany(ContributionFrequency::class);
    }

    /**
     * @return HasMany<PenaltyType, $this>
     */
    public function penaltyTypes(): HasMany
    {
        return $this->hasMany(PenaltyType::class);
    }

    /**
     * @return HasMany<LoanInterestMethod, $this>
     */
    public function loanInterestMethods(): HasMany
    {
        return $this->hasMany(LoanInterestMethod::class);
    }

    /**
     * @return HasMany<MembershipType, $this>
     */
    public function membershipTypes(): HasMany
    {
        return $this->hasMany(MembershipType::class);
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * @return HasMany<GroupRole, $this>
     */
    public function groupRoles(): HasMany
    {
        return $this->hasMany(GroupRole::class);
    }

    /**
     * @return HasMany<FinancialCycle, $this>
     */
    public function financialCycles(): HasMany
    {
        return $this->hasMany(FinancialCycle::class);
    }

    /**
     * @return HasMany<PaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * @return HasMany<FundType, $this>
     */
    public function fundTypes(): HasMany
    {
        return $this->hasMany(FundType::class);
    }

    /**
     * @return HasMany<GroupFund, $this>
     */
    public function groupFunds(): HasMany
    {
        return $this->hasMany(GroupFund::class);
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * @return HasMany<RepaymentFrequency, $this>
     */
    public function repaymentFrequencies(): HasMany
    {
        return $this->hasMany(RepaymentFrequency::class);
    }

    /**
     * @return HasMany<LoanType, $this>
     */
    public function loanTypes(): HasMany
    {
        return $this->hasMany(LoanType::class);
    }

    /**
     * @return HasMany<LoanProduct, $this>
     */
    public function loanProducts(): HasMany
    {
        return $this->hasMany(LoanProduct::class);
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
     * @return HasMany<FineType, $this>
     */
    public function fineTypes(): HasMany
    {
        return $this->hasMany(FineType::class);
    }

    /**
     * @return HasMany<GroupExpenseCategory, $this>
     */
    public function groupExpenseCategories(): HasMany
    {
        return $this->hasMany(GroupExpenseCategory::class);
    }

    /**
     * @return HasMany<MemberFine, $this>
     */
    public function memberFines(): HasMany
    {
        return $this->hasMany(MemberFine::class);
    }

    /**
     * @return HasMany<IncomeCategory, $this>
     */
    public function incomeCategories(): HasMany
    {
        return $this->hasMany(IncomeCategory::class);
    }

    /**
     * @return HasOne<GroupSetting, $this>
     */
    public function groupSetting(): HasOne
    {
        return $this->hasOne(GroupSetting::class);
    }

    /**
     * The group's settings, created from the group's first lookup rows when it has none yet.
     */
    public function ensureGroupSetting(): GroupSetting
    {
        return $this->groupSetting()->firstOr(fn () => $this->groupSetting()->create([
            'contribution_frequency_id' => $this->contributionFrequencies()->orderBy('display_order')->orderBy('id')->value('id'),
            'loan_interest_method_id' => $this->loanInterestMethods()->orderBy('display_order')->orderBy('id')->value('id'),
            'penalty_type_id' => $this->penaltyTypes()->orderBy('display_order')->orderBy('id')->value('id'),
        ]));
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
     * @return HasMany<ShareOut, $this>
     */
    public function shareOuts(): HasMany
    {
        return $this->hasMany(ShareOut::class);
    }

    /**
     * @return HasMany<MemberShareTransaction, $this>
     */
    public function memberShareTransactions(): HasMany
    {
        return $this->hasMany(MemberShareTransaction::class);
    }
}
