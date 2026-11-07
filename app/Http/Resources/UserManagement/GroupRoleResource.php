<?php

namespace App\Http\Resources\UserManagement;

use App\Models\UserManagement\GroupRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupRole
 */
class GroupRoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system_role' => $this->is_system_role,
            'status' => $this->status,
            'members_count' => $this->whenCounted('groupMemberRoles'),
        ];
    }
}
