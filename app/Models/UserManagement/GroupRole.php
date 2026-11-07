<?php

namespace App\Models\UserManagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'description', 'is_system_role', 'status'])]
class GroupRole extends Model
{
    /**
     * System roles that may manage a group's members and roles.
     *
     * @var array<int, string>
     */
    public const LEADERSHIP = ['Chairperson', 'Vice Chairperson', 'Secretary'];

    public const CHAIRPERSON = 'Chairperson';

    public const TREASURER = 'Treasurer';

    public const MEMBER = 'Member';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system_role' => 'boolean',
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
     * @return HasMany<GroupMemberRole, $this>
     */
    public function groupMemberRoles(): HasMany
    {
        return $this->hasMany(GroupMemberRole::class);
    }

    /**
     * @return BelongsToMany<GroupMember, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(GroupMember::class, 'group_member_roles')
            ->withPivot(['starts_at', 'ends_at', 'status'])
            ->withTimestamps();
    }
}
