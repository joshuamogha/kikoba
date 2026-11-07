<?php

namespace App\Http\Requests\PaymentManagement;

use App\Http\Controllers\PaymentManagement\LookupController;
use Illuminate\Validation\Rule;

class StoreLookupRequest extends GroupScopedRequest
{
    /**
     * The lookup definition for the current route.
     *
     * @return array{model: class-string, title: string, table: string, code: bool, lock_code?: bool, interval?: bool}
     */
    protected function lookup(): array
    {
        return LookupController::definition($this->route()->defaults['lookup']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lookup = $this->lookup();
        $groupId = $this->group()->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique($lookup['table'], 'name')->where('group_id', $groupId)],
            'description' => ['nullable', 'string', 'max:500'],
            ...($lookup['code'] ? ['code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique($lookup['table'], 'code')->where('group_id', $groupId)]] : []),
            ...($lookup['interval'] ?? false ? ['interval_days' => ['required', 'integer', 'min:1', 'max:365']] : []),
        ];
    }
}
