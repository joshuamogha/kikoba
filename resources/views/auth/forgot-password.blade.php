@extends('auth.layout')

@section('title', 'Forgot password')
@section('welcome_title', 'Forgot your password?')
@section('welcome_text', 'No problem. Tell us who you are and we will email you a reset link.')

@section('content')
    <h2>Reset your password.</h2>
    <p>Enter the phone number or email on your account and we will send a reset link to your email address.</p>

    @if (session('status'))
        <div class="sign-in-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-auth.field name="login" label="Phone number or email" autocomplete="username" autofocus />

        <button type="submit" class="btn btn-primary">Email reset link <span aria-hidden="true">↗</span></button>
    </form>

    <div class="sign-in-links">
        <a href="{{ route('login') }}">← Back to sign in</a>
    </div>
@endsection
