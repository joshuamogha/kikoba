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

/**
 * @return array<string, mixed>
 */
function fundPayload($group, array $overrides = []): array
{
    return [
        'group_id' => $group->id,
        'name' => 'Education Fund',
        'code' => 'edu',
        'fund_type_id' => $group->fundTypes()->value('id'),
        'contribution_frequency_id' => $group->contributionFrequencies()->value('id'),
        'minimum_contribution' => 5000,
        'is_mandatory' => true,
        'is_distributable' => false,
        'is_loanable' => true,
        ...$overrides,
    ];
}

test('the page lists funds of the chosen group only', function () {
    createFundFor($this->group);
    createFundFor($this->other);

    $this->actingAs($this->plain)->get(route('group-funds.index'))->assertOk()->assertSee('<group-funds', false);

    $this->actingAs($this->plain)->getJson(route('group-funds.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false);

    $this->actingAs($this->treasurer)->getJson(route('group-funds.index', ['group_id' => $this->group->id]))
        ->assertJsonCount(4, 'options.fund_types')->assertJsonPath('can_manage', true);
});

test('leaders create funds with an upper-case code and plain members cannot', function () {
    $this->actingAs($this->chair)->postJson(route('group-funds.store'), fundPayload($this->group))
        ->assertCreated()->assertJsonPath('data.code', 'EDU')->assertJsonPath('data.status', 'active');

    $this->actingAs($this->plain)->postJson(route('group-funds.store'), fundPayload($this->group, ['code' => 'X']))->assertForbidden();
});

test('fund codes are unique per group and the fund type must belong to the group', function () {
    createFundFor($this->group, ['code' => 'EDU']);

    $this->actingAs($this->chair)->postJson(route('group-funds.store'), fundPayload($this->group, ['code' => 'EDU', 'fund_type_id' => $this->other->fundTypes()->value('id')]))
        ->assertUnprocessable()->assertJsonValidationErrors(['code', 'fund_type_id']);
});

test('a fund can be deactivated and a fund of another group cannot be edited', function () {
    $fund = createFundFor($this->group);
    $foreign = createFundFor($this->other);

    $this->actingAs($this->chair)->putJson(route('group-funds.update', $fund), [...fundPayload($this->group, ['code' => 'SAV']), 'status' => 'inactive'])
        ->assertOk()->assertJsonPath('data.status', 'inactive');

    $this->actingAs($this->chair)->putJson(route('group-funds.update', $foreign), [...fundPayload($this->group), 'status' => 'inactive'])->assertNotFound();
});
