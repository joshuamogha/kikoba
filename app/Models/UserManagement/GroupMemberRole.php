<?php

namespace App\Models\UserManagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_member_id', 'group_role_id', 'starts_at', 'ends_at', 'status', 'assigned_by'])]
class GroupMemberRole extends Model
{
    /**
     * Assignments that are currently in force.
     *
     * @param  Builder<GroupMemberRole>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('group_member_roles.status', 'active')
            ->where(fn ($inner) => $inner->whereNull('group_member_roles.ends_at')
                ->orWhere('group_member_roles.ends_at', '>=', now()->toDateString()));
    }

    /**
     * End the assignment today, keeping it as history.
     */
    public function end(): void
    {
        $this->update(['status' => 'ended', 'ends_at' => now()->toDateString()]);
    }

    /**
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    /**
     * @return BelongsTo<GroupRole, $this>
     */
    public function groupRole(): BelongsTo
    {
        return $this->belongsTo(GroupRole::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
