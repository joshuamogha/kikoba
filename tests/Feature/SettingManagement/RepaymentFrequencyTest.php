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

test('repayment frequencies are listed per group with their interval', function () {
    $own = $this->actingAs($this->plain)->getJson(route('repayment-frequencies.index', ['group_id' => $this->group->id]))->assertOk()->assertJsonPath('can_manage', false);
    $foreign = $this->actingAs($this->other->creator)->getJson(route('repayment-frequencies.index', ['group_id' => $this->other->id]))->assertOk();

    expect($own->json('data.0.interval_days'))->toBe(7)
        ->and(collect($own->json('data'))->pluck('id')->intersect(collect($foreign->json('data'))->pluck('id')))->toBeEmpty();

    $this->actingAs($this->outsider)->getJson(route('repayment-frequencies.index', ['group_id' => $this->group->id]))->assertForbidden();
    $this->actingAs($this->plain)->get(route('repayment-frequencies.index'))->assertOk()->assertSee('has-interval', false);
});

test('leaders add and change repayment frequencies, plain members cannot', function () {
    $id = $this->actingAs($this->chair)->postJson(route('repayment-frequencies.store'), ['group_id' => $this->group->id, 'name' => 'Every 10 days', 'interval_days' => 10])
        ->assertCreated()->assertJsonPath('data.interval_days', 10)->json('data.id');

    $this->actingAs($this->chair)->putJson(route('repayment-frequencies.update', $id), ['group_id' => $this->group->id, 'name' => 'Every 20 days', 'interval_days' => 20, 'is_active' => true])
        ->assertOk()->assertJsonPath('data.interval_days', 20);

    $this->actingAs($this->plain)->postJson(route('repayment-frequencies.store'), ['group_id' => $this->group->id, 'name' => 'Nope', 'interval_days' => 5])->assertForbidden();
});

test('the interval must be a whole number of days up to a year', function () {
    foreach ([0, 400, 'abc', null] as $bad) {
        $this->actingAs($this->chair)->postJson(route('repayment-frequencies.store'), ['group_id' => $this->group->id, 'name' => 'Odd', 'interval_days' => $bad])
            ->assertUnprocessable()->assertJsonValidationErrors('interval_days');
    }
});

test('the finance settings page has a repayment frequencies tab and the sidebar no longer shows stray items', function () {
    $this->actingAs($this->plain)->get(route('setting-pages.show', 'finance'))->assertOk()->assertSee('repayment-frequencies');

    $this->actingAs($this->plain)->get(route('dashboard'))->assertDontSee('Repayment Frequencies')->assertDontSee('Fine Payments');
});
