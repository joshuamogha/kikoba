<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\UserManagement\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot password form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Queue a password reset link for the account matching the email or phone number.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $login = trim($request->string('login')->toString());

        $email = filter_var($login, FILTER_VALIDATE_EMAIL) !== false
            ? Str::lower($login)
            : User::query()->where('phone_number', User::normalizePhone($login))->value('email');

        if ($email !== null) {
            $status = Password::sendResetLink(['email' => $email]);

            if ($status === Password::RESET_THROTTLED) {
                return back()->withInput($request->only('login'))->withErrors(['login' => __($status)]);
            }
        }

        return back()->with('status', 'If an account matches, a password reset link has been sent to its email address.');
    }
}
