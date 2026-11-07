<?php

namespace App\Http\Requests\ShareManagement;

use Illuminate\Foundation\Http\FormRequest;

class ImportMemberSharesRequest extends FormRequest
{
    /**
     * Leaders and the Treasurer record shares.
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
