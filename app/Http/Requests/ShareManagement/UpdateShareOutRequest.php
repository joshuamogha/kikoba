<?php

namespace App\Http\Requests\ShareManagement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShareOutRequest extends FormRequest
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
            'calculation_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
