<?php

namespace App\Http\Resources\SettingManagement;

use App\Models\SettingManagement\FineType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FineType
 */
class FineTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'default_amount' => (float) $this->default_amount,
            'calculation_type' => $this->calculation_type,
            'status' => $this->status,
        ];
    }
}
