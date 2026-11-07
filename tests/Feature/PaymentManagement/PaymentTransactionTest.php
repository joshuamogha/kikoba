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
    $this->savings = createFundFor($this->group);
});

function recordApprovedContribution($test, $member): void
{
    $id = $test->actingAs($test->chair)->postJson(route('contributions.store'), [
        'group_id' => $test->group->id,
        'group_member_id' => $member->id,
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'contribution_date' => now()->toDateString(),
        'items' => [['group_fund_id' => $test->savings->id, 'amount' => 1000]],
    ])->json('data.id');

    $test->actingAs($test->chair)->patchJson(route('contributions.approve', $id))->assertOk();
}

test('leaders see every transaction of the chosen group', function () {
    recordApprovedContribution($this, $this->plainMember);
    recordApprovedContribution($this, $this->treasurerMember);

    $this->actingAs($this->chair)->get(route('payment-transactions.index'))->assertOk()->assertSee('<payment-transactions', false);

    $this->actingAs($this->chair)->getJson(route('payment-transactions.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('can_manage', true);

    $this->actingAs($this->other->creator)->getJson(route('payment-transactions.index', ['group_id' => $this->other->id]))
        ->assertOk()->assertJsonCount(0, 'data');
});

test('plain members only see their own transactions and outsiders see none', function () {
    recordApprovedContribution($this, $this->plainMember);
    recordApprovedContribution($this, $this->treasurerMember);

    $this->actingAs($this->plain)->getJson(route('payment-transactions.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false);

    $this->actingAs($this->outsider)->getJson(route('payment-transactions.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('transactions can be searched by number', function () {
    recordApprovedContribution($this, $this->plainMember);

    $number = $this->group->paymentTransactions()->value('transaction_number');

    $this->actingAs($this->chair)->getJson(route('payment-transactions.index', ['group_id' => $this->group->id, 'q' => $number]))->assertJsonCount(1, 'data');
    $this->actingAs($this->chair)->getJson(route('payment-transactions.index', ['group_id' => $this->group->id, 'q' => 'nothing-like-this']))->assertJsonCount(0, 'data');
});
