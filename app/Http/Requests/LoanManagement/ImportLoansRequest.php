<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;

class ImportLoansRequest extends GroupScopedRequest
{
    /**
     * Leaders and the Treasurer record loans.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageLoans', $this->group());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_guarantors' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
        ];
    }
}
