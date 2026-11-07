<?php

namespace App\Http\Requests\LoanManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondLoanGuarantorRequest extends FormRequest
{
    /**
     * The guarantor, or leaders and the Treasurer on their behalf, may respond.
     */
    public function authorize(): bool
    {
        $guarantor = $this->route('loan_guarantor');

        return $guarantor->groupMember->user_id === $this->user()->id
            || $this->user()->can('manageLoans', $guarantor->loanApplication->group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'declined'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
