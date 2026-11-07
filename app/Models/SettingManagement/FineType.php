<?php

namespace App\Models\SettingManagement;

use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'code', 'description', 'default_amount', 'calculation_type', 'status'])]
class FineType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
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
     * @return HasMany<MemberFine, $this>
     */
    public function memberFines(): HasMany
    {
        return $this->hasMany(MemberFine::class);
    }
}
