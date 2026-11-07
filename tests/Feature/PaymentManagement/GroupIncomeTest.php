<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    $this->treasurerMember = addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->outsider = User::factory()->create();
    $this->other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
});

beforeEach(function () {
    $this->fund = createFundFor($this->group, ['name' => 'General Fund', 'code' => 'GEN']);
});

/**
 * @return array<string, mixed>
 */
function incomePayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_fund_id' => $test->fund->id,
        'income_category_id' => $test->group->incomeCategories()->value('id'),
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'income_date' => now()->toDateString(),
        'description' => 'Registration of new members',
        'amount' => 30000,
        ...$overrides,
    ];
}

test('the page lists the chosen group\'s incomes for any member', function () {
    $this->actingAs($this->chair)->postJson(route('group-incomes.store'), incomePayload($this))->assertCreated();

    $this->actingAs($this->plain)->get(route('group-incomes.index'))->assertOk()->assertSee('<group-incomes', false);
    $this->actingAs($this->plain)->getJson(route('group-incomes.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('options', null);
    $this->actingAs($this->outsider)->getJson(route('group-incomes.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('an income is recorded pending and approving it logs a transaction', function () {
    $response = $this->actingAs($this->treasurer)->postJson(route('group-incomes.store'), incomePayload($this))
        ->assertCreated()->assertJsonPath('data.status', 'pending');

    $income = $this->group->groupIncomes()->sole();

    expect($income->income_number)->toBe(sprintf('INC-%d-%06d', now()->year, $income->id));

    $this->actingAs($this->treasurer)->patchJson(route('group-incomes.approve', $response->json('data.id')))->assertOk()->assertJsonPath('data.status', 'approved');
    $this->actingAs($this->treasurer)->patchJson(route('group-incomes.approve', $response->json('data.id')))->assertUnprocessable();

    expect($this->group->paymentTransactions()->sole()->transaction_type)->toBe('income');
});

test('plain members cannot record or approve incomes and foreign lookups are rejected', function () {
    $this->actingAs($this->plain)->postJson(route('group-incomes.store'), incomePayload($this))->assertForbidden();

    $id = $this->actingAs($this->chair)->postJson(route('group-incomes.store'), incomePayload($this))->json('data.id');
    $this->actingAs($this->plain)->patchJson(route('group-incomes.approve', $id))->assertForbidden();

    $this->actingAs($this->chair)->postJson(route('group-incomes.store'), incomePayload($this, [
        'income_category_id' => $this->other->incomeCategories()->value('id'),
        'amount' => -5,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['income_category_id', 'amount']);
});
