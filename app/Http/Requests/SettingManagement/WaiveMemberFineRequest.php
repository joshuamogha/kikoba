<?php

namespace App\Http\Requests\SettingManagement;

use Illuminate\Foundation\Http\FormRequest;

class WaiveMemberFineRequest extends FormRequest
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
        return [
            'waiver_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
