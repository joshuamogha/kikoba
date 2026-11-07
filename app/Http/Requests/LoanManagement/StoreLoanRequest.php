<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends GroupScopedRequest
{
    /**
     * Leaders and the Treasurer disburse loans.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageLoans', $this->group());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_application_id' => [
                'required', 'integer',
                Rule::exists('loan_applications', 'id')->where('group_id', $this->group()->id)->where('status', 'approved'),
                Rule::unique('loans', 'loan_application_id'),
            ],
            'disbursement_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
