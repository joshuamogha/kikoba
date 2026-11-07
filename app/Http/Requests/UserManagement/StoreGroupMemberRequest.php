<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'membership_type_id' => ['required', 'integer', Rule::exists('membership_types', 'id')->where('group_id', $this->route('group')->id)->where('is_active', true)],
            'joined_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['login' => 'phone number or email', 'name' => 'full name', 'membership_type_id' => 'membership type', 'joined_at' => 'joined date'];
    }
}
