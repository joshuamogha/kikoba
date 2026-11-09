<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $groupId = $this->route('group')->id;
        $lookup = fn (string $table): array => [
            'required', 'integer',
            Rule::exists($table, 'id')->where('group_id', $groupId)->where('is_active', true),
        ];

        return [
            'contribution_frequency_id' => $lookup('contribution_frequencies'),
            'loan_interest_method_id' => $lookup('loan_interest_methods'),
            'penalty_type_id' => $lookup('penalty_types'),
            'minimum_contribution' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'maximum_contribution' => ['nullable', 'numeric', 'gte:minimum_contribution', 'max:9999999999999999'],
            'share_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'minimum_shares' => ['required', 'integer', 'min:0', 'max:1000000'],
            'maximum_shares' => ['nullable', 'integer', 'gte:minimum_shares', 'max:1000000'],
            'default_loan_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_interest_period' => ['sometimes', Rule::in(['per_loan', 'per_month', 'per_year'])],
            'maximum_loan_multiplier' => ['required', 'numeric', 'min:0', 'max:1000'],
            'default_loan_duration_months' => ['required', 'integer', 'min:1', 'max:600'],
            'loan_grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'late_payment_penalty_value' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'loan_guarantors_required' => ['required', 'integer', 'min:0', 'max:50'],
            'withdrawal_approvals_required' => ['required', 'integer', 'min:0', 'max:50'],
            'opening_balance' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999999'],
            'opening_balance_date' => ['nullable', 'date', 'before_or_equal:today', Rule::requiredIf(fn () => (float) $this->input('opening_balance', 0) > 0)],
            'allow_partial_contributions' => ['required', 'boolean'],
            'allow_multiple_loans' => ['required', 'boolean'],
        ];
    }
}
