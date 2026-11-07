<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\UserManagement\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Create the account and send the (queued) verification email.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = new User($request->validated());
        $user->forceFill(['is_active' => true, 'status' => 'active'])->save();

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
