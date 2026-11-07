@extends('auth.layout')

@section('title', 'Verify email')
@section('welcome_title', 'One last step')
@section('welcome_text', 'Verify your email address to unlock your dashboard.')

@section('content')
    <h2>Check your inbox.</h2>
    <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to activate your account.</p>

    @if (session('status') === 'verification-link-sent')
        <div class="sign-in-status">A new verification link has been sent to your email address.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary mt-4">Resend verification email <span aria-hidden="true">↗</span></button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="sign-in-links">
        @csrf
        <button type="submit" class="btn btn-link p-0 small fw-semibold">Sign out</button>
    </form>
@endsection
