<?php

use App\Models\UserManagement\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function registrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Amina Juma',
        'phone_number' => '0712 345 678',
        'email' => 'Amina@Example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

test('the registration page is displayed', function () {
    $this->get(route('register'))->assertOk()->assertSee('Create your account.');
});

test('new users can register and are sent a queued verification email', function () {
    Notification::fake();

    $this->post(route('register'), registrationData())
        ->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'amina@example.com')->firstOrFail();

    expect($user->phone_number)->toBe('0712345678')
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('the verification notification is queued', function () {
    expect(new QueuedVerifyEmail)->toBeInstanceOf(ShouldQueue::class);
});

test('registration validates its input', function (array $overrides, string $field) {
    $this->post(route('register'), registrationData($overrides))->assertSessionHasErrors($field);

    $this->assertGuest();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'bad phone' => [['phone_number' => 'abc'], 'phone_number'],
    'missing email' => [['email' => ''], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched password' => [['password_confirmation' => 'different'], 'password'],
]);

test('duplicate phone numbers and emails are rejected', function () {
    User::factory()->create(['phone_number' => '0712345678', 'email' => 'amina@example.com']);

    $this->post(route('register'), registrationData())
        ->assertSessionHasErrors(['phone_number', 'email']);
});
