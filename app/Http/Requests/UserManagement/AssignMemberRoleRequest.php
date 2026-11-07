<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMemberRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageRoles', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $groupId = $this->route('group')->id;

        return [
            'group_role_id' => [
                'required', 'integer',
                Rule::exists('group_roles', 'id')
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('group_id')->orWhere('group_id', $groupId))
                    ->whereNot('name', 'Member'),
            ],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['group_role_id' => 'role'];
    }
}
