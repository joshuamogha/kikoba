<?php

namespace App\Http\Requests\ShareManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShareOutPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageShareOuts', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_share_out_id' => ['required', 'integer', Rule::exists('member_share_outs', 'id')->where('share_out_id', $this->route('share_out')->id)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('group_id', $this->route('group')->id)],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'external_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
