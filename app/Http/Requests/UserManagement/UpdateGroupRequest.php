<?php

namespace App\Http\Requests\UserManagement;

use App\Models\UserManagement\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');
        $currentMembers = $group->groupMembers()->where('membership_status', '!=', 'exited')->count();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'registration_number' => ['nullable', 'string', 'max:100', Rule::unique('groups', 'registration_number')->ignore($group->id)],
            'established_date' => ['nullable', 'date', 'before_or_equal:today'],
            'maximum_members' => ['nullable', 'integer', 'min:'.max(2, $currentMembers), 'max:100000'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['maximum_members' => 'maximum members', 'registration_number' => 'registration number', 'established_date' => 'established date'];
    }
}
