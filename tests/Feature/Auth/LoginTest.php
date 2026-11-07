<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('the login page is displayed', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Phone number or email')
        ->assertSee('Forgot password?');
});

test('users can sign in with their email', function () {
    $user = User::factory()->create(['email' => 'amina@example.com']);

    $this->post(route('login'), ['login' => 'AMINA@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('users can sign in with their phone number', function () {
    $user = User::factory()->create(['phone_number' => '0712345678']);

    $this->post(route('login'), ['login' => '0712 345-678', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot sign in with a wrong password', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['login' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('inactive users cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login'), ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('sign in is rate limited after repeated failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['login' => $user->email, 'password' => 'wrong']);
    }

    $this->post(route('login'), ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();

    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
});

test('authenticated users are redirected away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('guests are redirected to login from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
