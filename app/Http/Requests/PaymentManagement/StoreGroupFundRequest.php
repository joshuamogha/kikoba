<?php

namespace App\Http\Requests\PaymentManagement;

use Illuminate\Validation\Rule;

class StoreGroupFundRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $this->uniqueCode()],
            'description' => ['nullable', 'string', 'max:500'],
            'fund_type_id' => ['required', 'integer', $this->inGroup('fund_types')],
            'contribution_frequency_id' => ['required', 'integer', $this->inGroup('contribution_frequencies')],
            'minimum_contribution' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'is_mandatory' => ['required', 'boolean'],
            'is_distributable' => ['required', 'boolean'],
            'is_loanable' => ['required', 'boolean'],
        ];
    }

    protected function uniqueCode(): mixed
    {
        return Rule::unique('group_funds', 'code')->where('group_id', $this->group()->id);
    }
}
