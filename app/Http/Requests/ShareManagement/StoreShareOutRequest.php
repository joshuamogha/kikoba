<?php

namespace App\Http\Requests\ShareManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShareOutRequest extends FormRequest
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
            'financial_cycle_id' => ['required', 'integer', Rule::exists('financial_cycles', 'id')->where('group_id', $this->route('group')->id)],
            'calculation_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
