<?php

namespace App\Http\Requests\PaymentManagement;

use Illuminate\Validation\Rule;

class UpdateGroupFundRequest extends StoreGroupFundRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function uniqueCode(): mixed
    {
        return Rule::unique('group_funds', 'code')->where('group_id', $this->group()->id)->ignore($this->route('group_fund')->id);
    }
}
