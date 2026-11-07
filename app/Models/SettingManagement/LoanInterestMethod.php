<?php

namespace App\Models\SettingManagement;

use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\LoanManagement\LoanProduct;
use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'code', 'description', 'display_order', 'is_active'])]
class LoanInterestMethod extends Model
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
     * @return HasMany<GroupSetting, $this>
     */
    public function groupSettings(): HasMany
    {
        return $this->hasMany(GroupSetting::class);
    }
}
