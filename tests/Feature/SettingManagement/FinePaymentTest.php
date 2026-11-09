<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();
    $this->other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
});

beforeEach(function () {
    $fineType = $this->group->fineTypes()->create(['name' => 'Absent', 'code' => 'ABSENT', 'default_amount' => 2000, 'calculation_type' => 'fixed', 'status' => 'active']);

    foreach ([$this->plainMember, $this->friendMember] as $member) {
        $fineId = $this->actingAs($this->chair)->postJson(route('member-fines.store'), [
            'group_id' => $this->group->id, 'group_member_id' => $member->id, 'fine_type_id' => $fineType->id,
            'reason' => 'Missed a meeting', 'fine_date' => now()->toDateString(), 'amount' => 2000,
        ])->json('data.id');

        $this->actingAs($this->chair)->postJson(route('member-fines.pay', $fineId), [
            'amount' => 1000, 'payment_method_id' => $this->group->paymentMethods()->value('id'), 'payment_date' => now()->toDateString(),
        ])->assertOk();
    }
});

test('leaders see every fine payment of the chosen group and the page redirects to the fines tab', function () {
    $this->actingAs($this->chair)->get(route('fine-payments.index'))->assertRedirect(route('payment-pages.show', 'fines'));

    $this->actingAs($this->chair)->getJson(route('fine-payments.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('can_manage', true)->assertJsonPath('data.0.amount', 1000);
    $this->actingAs($this->other->creator)->getJson(route('fine-payments.index', ['group_id' => $this->other->id]))->assertJsonCount(0, 'data');
});

test('members only see their own fine payments and outsiders none', function () {
    $this->actingAs($this->plain)->getJson(route('fine-payments.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false);
    $this->actingAs($this->outsider)->getJson(route('fine-payments.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('fine payments can be searched by member name or number', function () {
    $this->actingAs($this->chair)->getJson(route('fine-payments.index', ['group_id' => $this->group->id, 'q' => $this->friend->name]))->assertJsonCount(1, 'data');
    $this->actingAs($this->chair)->getJson(route('fine-payments.index', ['group_id' => $this->group->id, 'q' => 'no-such-thing']))->assertJsonCount(0, 'data');
});

test('the fines settings page has a fine payments tab', function () {
    $this->actingAs($this->plain)->get(route('payment-pages.show', 'fines'))->assertOk()->assertSee('fine-payments');
});
