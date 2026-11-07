<?php

namespace App\Models\PaymentManagement;

use App\Models\LoanManagement\LoanProduct;
use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'interval_days', 'description', 'display_order', 'is_active'])]
class RepaymentFrequency extends Model
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
}
