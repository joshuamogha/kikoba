<?php

namespace App\Http\Requests\UserManagement;

use App\Models\UserManagement\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('group'));
    }

    /**
     * Normalise the phone number before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone_number')) {
            $this->merge(['phone_number' => User::normalizePhone($this->string('phone_number')->toString())]);
        }
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
            'phone_number' => [
                'nullable', 'regex:/^\+?[0-9]{9,15}$/',
                Rule::unique('group_members', 'phone_number')
                    ->where('group_id', $this->route('group')->id)
                    ->ignore($this->route('group_member')->id),
            ],
            'membership_type_id' => ['required', 'integer', Rule::exists('membership_types', 'id')->where('group_id', $this->route('group')->id)->where('is_active', true)],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'full name', 'phone_number' => 'phone number', 'membership_type_id' => 'membership type'];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Enter a valid phone number, for example 0712345678 or +255712345678.',
            'phone_number.unique' => 'Another member of this group already has that phone number.',
        ];
    }
}
