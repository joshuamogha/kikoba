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
function fineTypePayload($group, array $overrides = []): array
{
    return [
        'group_id' => $group->id,
        'name' => 'Late to meeting',
        'code' => 'late_meeting',
        'default_amount' => 1000,
        'calculation_type' => 'fixed',
        ...$overrides,
    ];
}

test('fine types are listed for the chosen group and the page redirects to the fines tab', function () {
    $this->group->fineTypes()->create([...fineTypePayload($this->group), 'status' => 'active']);
    $this->other->fineTypes()->create([...fineTypePayload($this->other), 'status' => 'active']);

    $this->actingAs($this->plain)->get(route('fine-types.index'))->assertRedirect(route('setting-pages.show', 'fines'));

    $this->actingAs($this->plain)->getJson(route('fine-types.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false);
    $this->actingAs($this->outsider)->getJson(route('fine-types.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('leaders and the treasurer create fine types with an upper-case code, plain members cannot', function () {
    $this->actingAs($this->treasurer)->postJson(route('fine-types.store'), fineTypePayload($this->group))
        ->assertCreated()->assertJsonPath('data.code', 'LATE_MEETING')->assertJsonPath('data.status', 'active');

    $this->actingAs($this->plain)->postJson(route('fine-types.store'), fineTypePayload($this->group, ['code' => 'X']))->assertForbidden();
});

test('fine type codes are unique per group and calculation types are limited', function () {
    $this->actingAs($this->chair)->postJson(route('fine-types.store'), fineTypePayload($this->group))->assertCreated();

    $this->actingAs($this->chair)->postJson(route('fine-types.store'), fineTypePayload($this->group, ['calculation_type' => 'random']))
        ->assertUnprocessable()->assertJsonValidationErrors(['code', 'calculation_type']);
    $this->actingAs($this->other->creator)->postJson(route('fine-types.store'), fineTypePayload($this->other))->assertCreated();
});

test('a fine type can be deactivated but not across groups', function () {
    $id = $this->actingAs($this->chair)->postJson(route('fine-types.store'), fineTypePayload($this->group))->json('data.id');
    $foreign = $this->other->fineTypes()->create([...fineTypePayload($this->other), 'status' => 'active']);

    $this->actingAs($this->chair)->putJson(route('fine-types.update', $id), [...fineTypePayload($this->group), 'status' => 'inactive'])
        ->assertOk()->assertJsonPath('data.status', 'inactive');
    $this->actingAs($this->chair)->putJson(route('fine-types.update', $foreign), [...fineTypePayload($this->group, ['code' => 'ZZ']), 'status' => 'inactive'])->assertNotFound();
});
