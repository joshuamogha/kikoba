<?php

use App\Models\UserManagement\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('the forgot password page is displayed', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('Reset your password.');
});

test('a reset link is queued when requested by email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['login' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('a reset link is queued when requested by phone number', function () {
    Notification::fake();
    $user = User::factory()->create(['phone_number' => '0712345678']);

    $this->post(route('password.email'), ['login' => '0712 345 678'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('unknown accounts get the same response and no email', function () {
    Notification::fake();

    $this->post(route('password.email'), ['login' => 'nobody@example.com'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('the reset password screen is displayed', function () {
    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@example.com']))
        ->assertOk()
        ->assertSee('Set a new password.');
});

test('the password can be reset with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

test('the password cannot be reset with an invalid token', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
