<?php

namespace App\Http\Requests\SettingManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use Illuminate\Validation\Rule;

class StoreFineTypeRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $this->uniqueCode()],
            'description' => ['nullable', 'string', 'max:500'],
            'default_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage'])],
        ];
    }

    protected function uniqueCode(): mixed
    {
        return Rule::unique('fine_types', 'code')->where('group_id', $this->group()->id);
    }
}
