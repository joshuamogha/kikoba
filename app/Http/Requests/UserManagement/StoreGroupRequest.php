<?php

namespace App\Http\Requests\UserManagement;

use App\Models\UserManagement\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Group::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'group_type_id' => ['required', 'integer', Rule::exists('group_types', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:1000'],
            'registration_number' => ['nullable', 'string', 'max:100', 'unique:groups,registration_number'],
            'established_date' => ['nullable', 'date', 'before_or_equal:today'],
            'maximum_members' => ['nullable', 'integer', 'min:2', 'max:100000'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['group_type_id' => 'group type', 'maximum_members' => 'maximum members', 'registration_number' => 'registration number', 'established_date' => 'established date'];
    }
}
