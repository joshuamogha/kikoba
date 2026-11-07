@extends('auth.layout')

@section('title', 'Reset password')
@section('welcome_title', 'Choose a new password')
@section('welcome_text', 'Pick something strong that you have not used before.')

@section('content')
    <h2>Set a new password.</h2>
    <p>Enter and confirm your new password below.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-auth.field name="email" label="Email address" type="email" :value="$email" autocomplete="username" />
        <x-auth.field name="password" label="New password" type="password" autocomplete="new-password" autofocus />
        <x-auth.field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" />

        <button type="submit" class="btn btn-primary">Reset password <span aria-hidden="true">↗</span></button>
    </form>

    <div class="sign-in-links">
        <a href="{{ route('login') }}">← Back to sign in</a>
    </div>
@endsection
