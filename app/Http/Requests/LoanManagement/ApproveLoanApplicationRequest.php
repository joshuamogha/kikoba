<?php

namespace App\Http\Requests\LoanManagement;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLoanApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageLoans', $this->route('loan_application')->group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $application = $this->route('loan_application');

        return [
            'approved_amount' => ['required', 'numeric', 'gt:0', 'lte:'.$application->requested_amount],
        ];
    }
}
