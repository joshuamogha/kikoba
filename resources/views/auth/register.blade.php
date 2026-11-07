@extends('auth.layout')

@section('title', 'Create account')
@section('welcome_title', 'Join your community')
@section('welcome_text', 'Create an account to manage your savings, contributions and loans in one place.')

@section('content')
    <h2>Create your account.</h2>
    <p>Fill in your details to get started. We will email you a link to verify your address.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <x-auth.field name="name" label="Full name" autocomplete="name" autofocus />
        <x-auth.field name="phone_number" label="Phone number" type="tel" autocomplete="tel" />
        <x-auth.field name="email" label="Email address" type="email" autocomplete="email" />
        <x-auth.field name="password" label="Password" type="password" autocomplete="new-password" />
        <x-auth.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />

        <button type="submit" class="btn btn-primary">Create account <span aria-hidden="true">↗</span></button>
    </form>

    <div class="sign-in-links">
        <span>Already have an account? <a href="{{ route('login') }}">Sign in</a></span>
    </div>
@endsection
