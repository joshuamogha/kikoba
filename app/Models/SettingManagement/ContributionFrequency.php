<?php

namespace App\Models\SettingManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\PaymentManagement\GroupFund;
use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'description', 'display_order', 'is_active'])]
class ContributionFrequency extends Model
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
     * @return HasMany<FinancialCycle, $this>
     */
    public function financialCycles(): HasMany
    {
        return $this->hasMany(FinancialCycle::class);
    }

    /**
     * @return HasMany<GroupFund, $this>
     */
    public function groupFunds(): HasMany
    {
        return $this->hasMany(GroupFund::class);
    }

    /**
     * @return HasMany<GroupSetting, $this>
     */
    public function groupSettings(): HasMany
    {
        return $this->hasMany(GroupSetting::class);
    }
}
