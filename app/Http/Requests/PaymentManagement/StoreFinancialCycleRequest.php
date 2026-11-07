<?php

namespace App\Http\Requests\PaymentManagement;

class StoreFinancialCycleRequest extends GroupScopedRequest
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
            'contribution_frequency_id' => ['required', 'integer', $this->inGroup('contribution_frequencies')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }
}
