<?php

namespace App\Http\Resources\UserManagement;

use App\Models\UserManagement\GroupType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupType
 */
class GroupTypeResource extends JsonResource
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
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
            'groups_count' => $this->whenCounted('groups'),
        ];
    }
}
