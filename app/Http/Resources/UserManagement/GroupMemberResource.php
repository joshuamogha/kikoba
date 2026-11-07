<?php

namespace App\Http\Resources\UserManagement;

use App\Models\UserManagement\GroupMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupMember
 */
class GroupMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array. The account email is only shown to people who manage the group.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = (bool) $request->attributes->get('can_manage', false);

        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'name' => $this->name,
            'initials' => $this->initials(),
            'phone' => $this->phone_number,
            'email' => $this->when($canManage && $this->isLinked(), fn () => $this->user->email),
            'has_account' => $this->isLinked(),
            'membership_type' => [
                'id' => $this->membershipType->id,
                'name' => $this->membershipType->name,
            ],
            'shares_count' => (int) ($this->shares_count ?? 0),
            'shares_amount' => (float) ($this->shares_amount ?? 0),
            'membership_status' => $this->membership_status,
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'exit_reason' => $this->exit_reason,
            'is_self' => $this->user_id !== null && $this->user_id === $request->user()->id,
            'roles' => $this->whenLoaded('groupMemberRoles', fn () => $this->groupMemberRoles->map(fn ($assignment) => [
                'assignment_id' => $assignment->id,
                'role_id' => $assignment->group_role_id,
                'name' => $assignment->groupRole->name,
                'since' => $assignment->starts_at,
            ])->values()),
        ];
    }
}
