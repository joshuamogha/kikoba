<?php

namespace App\Http\Requests\SettingManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('managePayments', $this->route('member_fine')->group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fine = $this->route('member_fine');

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.$fine->balance],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('group_id', $fine->group_id)->where('is_active', true)],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
