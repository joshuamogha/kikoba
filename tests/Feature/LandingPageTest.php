<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the landing page is public and describes what the application does', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Contributions &amp; funds', false)
        ->assertSee('Shares')
        ->assertSee('Loans')
        ->assertSee('Financial cycles')
        ->assertSee('Member statement')
        ->assertSee('Treasurer')
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertDontSee(route('dashboard'), false);
});

test('a signed-in user is offered the dashboard instead of the sign-in links', function () {
    $this->actingAs(User::factory()->create())->get('/')
        ->assertOk()
        ->assertSee('Open dashboard')
        ->assertSee(route('dashboard'), false)
        ->assertDontSee('Get started');
});

test('the dashboard stays behind sign-in', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('every sign-in page links back to the landing page', function () {
    foreach (['login', 'register', 'password.request'] as $name) {
        $this->get(route($name))->assertOk()->assertSee('Back to home')->assertSee('href="'.route('home').'"', false);
    }
});

test('the create your group buttons lead to sign in, not to registration', function () {
    $html = $this->get('/')->getContent();

    preg_match_all('/<a href="([^"]+)"[^>]*>Create your group<\/a>/', $html, $matches);

    expect($matches[1])->toHaveCount(2)->and(array_unique($matches[1]))->toBe([route('login')]);
});
