<?php

namespace App\Http\Requests\PaymentManagement;

use App\Models\UserManagement\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Base for payment-management writes: the group comes from the group_id input and only its leaders and Treasurer may write.
 */
abstract class GroupScopedRequest extends FormRequest
{
    private ?Group $resolvedGroup = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('managePayments', $this->group());
    }

    /**
     * Codes are stored in upper case, so compare them in upper case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * The group the request writes to.
     */
    public function group(): Group
    {
        return $this->resolvedGroup ??= Group::query()->findOrFail($this->integer('group_id'));
    }

    /**
     * A rule for an id that must belong to this group and be active.
     */
    protected function inGroup(string $table, string $activeColumn = 'is_active', mixed $activeValue = true): Exists
    {
        return Rule::exists($table, 'id')
            ->where('group_id', $this->group()->id)
            ->where($activeColumn, $activeValue);
    }
}
