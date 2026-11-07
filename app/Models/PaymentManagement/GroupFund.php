<?php

namespace App\Models\PaymentManagement;

use App\Models\LoanManagement\LoanProduct;
use App\Models\SettingManagement\ContributionFrequency;
use App\Models\SettingManagement\FundType;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'fund_type_id', 'name', 'code', 'description', 'minimum_contribution', 'contribution_frequency_id', 'is_mandatory', 'is_distributable', 'is_loanable', 'status', 'created_by'])]
class GroupFund extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_contribution' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'is_distributable' => 'boolean',
            'is_loanable' => 'boolean',
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
     * @return BelongsTo<FundType, $this>
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ContributionItem, $this>
     */
    public function contributionItems(): HasMany
    {
        return $this->hasMany(ContributionItem::class);
    }

    /**
     * @return HasMany<LoanProduct, $this>
     */
    public function loanProducts(): HasMany
    {
        return $this->hasMany(LoanProduct::class);
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
}
