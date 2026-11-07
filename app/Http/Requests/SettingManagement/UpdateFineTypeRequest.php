<?php

namespace App\Http\Requests\SettingManagement;

use Illuminate\Validation\Rule;

class UpdateFineTypeRequest extends StoreFineTypeRequest
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
        return Rule::unique('fine_types', 'code')->where('group_id', $this->group()->id)->ignore($this->route('fine_type')->id);
    }
}
