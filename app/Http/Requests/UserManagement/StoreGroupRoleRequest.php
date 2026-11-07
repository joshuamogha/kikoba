<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageRoles', $this->route('group'));
    }

    /**
     * Custom roles may not reuse a system role name.
     */
    private function notASystemRoleName(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $taken = collect(config('group_defaults.system_roles'))
                ->contains(fn (array $role) => strcasecmp($role['name'], (string) $value) === 0);

            if ($taken) {
                $fail('That name is reserved for a system role.');
            }
        };
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('group_roles', 'name')->where('group_id', $this->route('group')->id),
                $this->notASystemRoleName(),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
