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
function cyclePayload($group, array $overrides = []): array
{
    return [
        'group_id' => $group->id,
        'name' => 'Cycle 2027',
        'contribution_frequency_id' => $group->contributionFrequencies()->value('id'),
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
        ...$overrides,
    ];
}

test('the page lists cycles of the chosen group and members cannot manage', function () {
    $this->actingAs($this->plain)->get(route('financial-cycles.index'))->assertOk()->assertSee('<financial-cycles', false);

    $this->actingAs($this->treasurer)->postJson(route('financial-cycles.store'), cyclePayload($this->group))->assertCreated();

    $this->actingAs($this->plain)->getJson(route('financial-cycles.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false);

    $this->actingAs($this->outsider)->getJson(route('financial-cycles.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('leaders and the treasurer create cycles, plain members cannot', function () {
    $this->actingAs($this->chair)->postJson(route('financial-cycles.store'), cyclePayload($this->group))
        ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.cycle_number', 1);

    $this->actingAs($this->plain)->postJson(route('financial-cycles.store'), cyclePayload($this->group))->assertForbidden();
});

test('a cycle must end after it starts and use this group\'s frequency', function () {
    $this->actingAs($this->chair)->postJson(route('financial-cycles.store'), cyclePayload($this->group, [
        'end_date' => '2026-12-31',
        'contribution_frequency_id' => $this->other->contributionFrequencies()->value('id'),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['end_date', 'contribution_frequency_id']);
});

test('a cycle moves from draft to open to closed and a closed cycle is locked', function () {
    $id = $this->actingAs($this->chair)->postJson(route('financial-cycles.store'), cyclePayload($this->group))->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('financial-cycles.close', $id))->assertUnprocessable();
    $this->actingAs($this->chair)->patchJson(route('financial-cycles.open', $id))->assertOk()->assertJsonPath('data.status', 'open');
    $this->actingAs($this->chair)->putJson(route('financial-cycles.update', $id), cyclePayload($this->group, ['name' => 'Renamed']))->assertOk()->assertJsonPath('data.name', 'Renamed');
    $this->actingAs($this->chair)->patchJson(route('financial-cycles.close', $id))->assertOk()->assertJsonPath('data.status', 'closed');
    $this->actingAs($this->chair)->putJson(route('financial-cycles.update', $id), cyclePayload($this->group))->assertUnprocessable();
    $this->actingAs($this->plain)->patchJson(route('financial-cycles.open', $id))->assertForbidden();
});
