<?php

namespace App\Http\Requests\Auth;

use App\Models\UserManagement\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the phone number and email before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => User::normalizePhone($this->string('phone_number')->toString()),
            'email' => Str::lower(trim($this->string('email')->toString())),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Password|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'regex:/^\+?[0-9]{9,15}$/', 'unique:users,phone_number'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Enter a valid phone number, for example 0712345678 or +255712345678.',
        ];
    }
}
