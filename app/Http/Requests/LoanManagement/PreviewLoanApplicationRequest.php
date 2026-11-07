<?php

namespace App\Http\Requests\LoanManagement;

use App\Models\UserManagement\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewLoanApplicationRequest extends FormRequest
{
    /**
     * Anyone who can see the group may preview a term.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', Group::query()->findOrFail($this->integer('group_id')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer'],
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')->where('group_id', $this->integer('group_id'))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'disbursement_date' => ['nullable', 'date'],
        ];
    }
}
