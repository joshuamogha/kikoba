<?php

use App\Models\UserManagement\User;
use App\Notifications\QueuedVerifyEmail;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('unverified users are sent to the verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);
});

test('the email can be verified with a signed link', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($user, WelcomeNotification::class);
});

test('the email is not verified with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('wrong-email'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

test('an unsigned verification link is rejected', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
        ->assertForbidden();
});

test('the verification email can be resent', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('verified users can open the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});
