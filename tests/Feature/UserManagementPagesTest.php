<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('initials use the first and last word of the name', function (string $name, string $initials) {
    expect(User::factory()->make(['name' => $name])->initials())->toBe($initials);
})->with([
    ['Neema Mushi', 'NM'],
    ['Joseph Kimaro Mollel', 'JM'],
    ['madonna', 'MA'],
]);

test('guests cannot see the users or profile pages', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->get(route('profile.show'))->assertRedirect(route('login'));
});

test('the navbar shows only the user menu with initials, profile and sign out', function () {
    $user = User::factory()->create(['name' => 'Neema Mushi']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('NM')
        ->assertSee(route('profile.show'), false)
        ->assertSee('Sign out')
        ->assertDontSee('Brad Diesel')
        ->assertDontSee('data-widget="navbar-search"', false);
});

test('the users page lists and searches users', function () {
    $viewer = User::factory()->platformAdmin()->create(['name' => 'Asha Juma']);
    User::factory()->create(['name' => 'Baraka Mollel', 'phone_number' => '0712000111']);
    User::factory()->create(['name' => 'Zainab Mrisho', 'phone_number' => '0765999888']);

    $this->actingAs($viewer)->get(route('users.index'))
        ->assertOk()
        ->assertSee('Baraka Mollel')
        ->assertSee('Zainab Mrisho');

    $this->actingAs($viewer)->get(route('users.index', ['q' => '0712000']))
        ->assertOk()
        ->assertSee('Baraka Mollel')
        ->assertDontSee('Zainab Mrisho');
});

test('the profile page shows the user and their group memberships', function () {
    $this->seed();
    $user = User::query()->where('name', 'Neema Mushi')->firstOrFail();

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Umoja wa Wanawake Kinondoni VICOBA')
        ->assertSee('Chairperson');
});

test('only platform admins can open the users page', function () {
    $this->actingAs(User::factory()->create())->get(route('users.index'))->assertForbidden();
    $this->actingAs(User::factory()->platformAdmin()->create())->get(route('users.index'))->assertOk();
});

test('the sidebar shows the platform admin menu only to platform admins', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('User Management')
        ->assertDontSee('PLATFORM ADMIN')
        ->assertDontSee('System Roles');

    $this->actingAs(User::factory()->platformAdmin()->create())->get(route('dashboard'))
        ->assertSee('PLATFORM ADMIN')
        ->assertSee(route('users.index'), false)
        ->assertSeeInOrder(['PLATFORM ADMIN', 'Platform Admin', 'Users', 'Groups', 'Group Types', 'System Roles']);
});
