<?php

namespace App\Http\Requests\UserManagement;

use App\Models\UserManagement\Group;
use Illuminate\Foundation\Http\FormRequest;

class ImportGroupMembersRequest extends FormRequest
{
    private ?Group $resolvedGroup = null;

    /**
     * Only leaders manage a group's members.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->group());
    }

    public function group(): Group
    {
        return $this->resolvedGroup ??= Group::query()->findOrFail($this->integer('group_id'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
