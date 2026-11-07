<?php

namespace App\Http\Requests\LoanManagement;

use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends StoreLoanProductRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function uniqueCode(): mixed
    {
        return Rule::unique('loan_products', 'code')->where('group_id', $this->group()->id)->ignore($this->route('loan_product')->id);
    }
}
