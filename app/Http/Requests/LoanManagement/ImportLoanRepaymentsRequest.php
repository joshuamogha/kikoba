<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;

class ImportLoanRepaymentsRequest extends GroupScopedRequest
{
    /**
     * Leaders and the Treasurer record repayments.
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
            'payment_method_id' => ['nullable', 'integer', $this->inGroup('payment_methods')],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
        ];
    }
}
