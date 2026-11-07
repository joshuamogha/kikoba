@extends('auth.layout')

@section('title', 'Sign in')
@section('welcome_title', 'Save together, grow together')
@section('welcome_text', 'Welcome back to your group. Sign in to continue.')

@section('content')
    <h2>Good to see you.</h2>
    <p>Enter your account details to sign in.</p>

    @if (session('status'))
        <div class="sign-in-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-auth.field name="login" label="Phone number or email" autocomplete="username" autofocus />
        <x-auth.field name="password" label="Password" type="password" autocomplete="current-password" />

        <div class="d-flex justify-content-between align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <a class="small fw-semibold" href="{{ route('password.request') }}">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary mt-4">Sign in <span aria-hidden="true">↗</span></button>
    </form>

    <div class="sign-in-links">
        <span>New here? <a href="{{ route('register') }}">Create account</a></span>
    </div>
@endsection
