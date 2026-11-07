<?php

namespace App\Http\Requests\PaymentManagement;

use Illuminate\Validation\Rule;

class UpdateLookupRequest extends StoreLookupRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lookup = $this->lookup();
        $groupId = $this->group()->id;
        $id = (int) $this->route('record');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique($lookup['table'], 'name')->where('group_id', $groupId)->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            ...($lookup['interval'] ?? false ? ['interval_days' => ['required', 'integer', 'min:1', 'max:365']] : []),
            ...($lookup['code'] && ! ($lookup['lock_code'] ?? false) ? ['code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique($lookup['table'], 'code')->where('group_id', $groupId)->ignore($id)]] : []),
        ];
    }
}
