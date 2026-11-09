<?php

namespace App\Http\Requests\SettingManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;

class ImportMemberFinesRequest extends GroupScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
        ];
    }
}
