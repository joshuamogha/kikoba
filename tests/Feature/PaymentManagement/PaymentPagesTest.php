<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->member = User::factory()->create();
    $this->group = createGroupFor($this->member);
});

dataset('payment pages', [
    'income' => ['income', ['group-incomes', 'income-categories']],
    'expenses' => ['expenses', ['group-expenses', 'group-expense-categories']],
]);

test('income and expenses are tabbed pages of two lists each', function (string $page, array $tabKeys) {
    $response = $this->actingAs($this->member)->get(route('payment-pages.show', $page))->assertOk()->assertSee('<setting-tabs', false);

    foreach ($tabKeys as $key) {
        $response->assertSee($key);
    }

    expect($response->viewData('groups'))->toHaveCount(1);
})->with('payment pages');

test('an unknown payment page is not found and guests are sent to sign in', function () {
    $this->actingAs($this->member)->get('/payments/unknown')->assertNotFound();

    auth()->logout();
    $this->get(route('payment-pages.show', 'income'))->assertRedirect();
});

test('the sidebar groups income and expenses and no longer lists contribution items or schedules', function () {
    $this->actingAs($this->member)->get(route('dashboard'))
        ->assertSee(route('payment-pages.show', 'income'), false)
        ->assertSee(route('payment-pages.show', 'expenses'), false)
        ->assertDontSee('Contribution Items')
        ->assertDontSee('Contribution Schedules');
});

test('fines are a payment page with member fines and fine payments, linked from the sidebar', function () {
    $this->actingAs($this->member)->get(route('payment-pages.show', 'fines'))
        ->assertOk()->assertSee('member-fines')->assertSee('fine-payments');

    $this->actingAs($this->member)->get(route('dashboard'))->assertSee(route('payment-pages.show', 'fines'), false);
});
