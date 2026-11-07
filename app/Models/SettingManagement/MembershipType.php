<?php

namespace App\Models\SettingManagement;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'description', 'display_order', 'is_active'])]
class MembershipType extends Model
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
     * @return HasMany<GroupMember, $this>
     */
    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}
