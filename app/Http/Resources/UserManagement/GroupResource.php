<?php

namespace App\Http\Resources\UserManagement;

use App\Models\UserManagement\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 */
class GroupResource extends JsonResource
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
            'status' => $this->status,
            'registration_number' => $this->registration_number,
            'established_date' => $this->established_date,
            'currency' => $this->currency,
            'maximum_members' => $this->maximum_members,
            'group_type' => $this->whenLoaded('groupType', fn () => [
                'id' => $this->groupType->id,
                'name' => $this->groupType->name,
            ]),
            'members_count' => $this->whenCounted('groupMembers'),
            'my_roles' => $this->whenLoaded('groupMembers', fn () => $this->groupMembers
                ->flatMap(fn ($member) => $member->groupMemberRoles->pluck('groupRole.name'))
                ->unique()->sort()->values()),
            'url' => route('groups.show', $this->id),
        ];
    }
}
