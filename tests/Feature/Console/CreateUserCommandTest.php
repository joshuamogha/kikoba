<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function createUserArguments(array $overrides = []): array
{
    return [
        '--name' => 'Neema Mushi',
        '--email' => 'Neema@Example.com',
        '--phone' => '0712 345 678',
        '--password' => 'Str0ng-Passw0rd!',
        ...$overrides,
    ];
}

test('a normal user is created active and verified, ready to sign in', function () {
    $this->artisan('users:create', createUserArguments())->assertSuccessful();

    $user = User::query()->where('email', 'neema@example.com')->sole();

    expect($user->phone_number)->toBe('0712345678')
        ->and($user->is_platform_admin)->toBeFalse()
        ->and($user->canSignIn())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->post(route('login'), ['login' => '0712345678', 'password' => 'Str0ng-Passw0rd!'])
        ->assertRedirect(route('dashboard'));
    expect(Auth::id())->toBe($user->id);
});

test('--admin creates a platform administrator', function () {
    $this->artisan('users:create', createUserArguments(['--admin' => true]))->assertSuccessful();

    expect(User::query()->sole()->is_platform_admin)->toBeTrue();
});

test('--unverified leaves the email unverified', function () {
    $this->artisan('users:create', createUserArguments(['--unverified' => true]))->assertSuccessful();

    expect(User::query()->sole()->email_verified_at)->toBeNull();
});

test('duplicates and weak passwords are rejected and nothing is created', function () {
    User::factory()->create(['email' => 'neema@example.com', 'phone_number' => '0712345678']);

    $this->artisan('users:create', createUserArguments())->assertFailed();
    $this->artisan('users:create', createUserArguments(['--email' => 'new@example.com', '--phone' => '0755000111', '--password' => 'abc']))->assertFailed();

    expect(User::query()->count())->toBe(1);
});

test('without prompts every value must be given as an option', function () {
    $this->artisan('users:create', ['--name' => 'Only Name', '--no-interaction' => true])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
