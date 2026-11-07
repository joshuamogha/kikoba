<?php

namespace App\Http\Requests\SettingManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;

class StoreMemberFineRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_member_id' => ['required', 'integer', $this->inGroup('group_members', 'membership_status', 'active')],
            'fine_type_id' => ['required', 'integer', $this->inGroup('fine_types', 'status', 'active')],
            'reason' => ['required', 'string', 'max:1000'],
            'fine_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:fine_date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
        ];
    }
}
