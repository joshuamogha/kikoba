<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->chairMember = $this->group->groupMembers()->where('user_id', $this->chair->id)->firstOrFail();
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();
});

function memberTypeId($test): int
{
    return (int) $test->group->membershipTypes()->orderBy('id')->value('id');
}

test('leaders edit a member from the members list endpoints', function () {
    $this->actingAs($this->chair)->putJson(route('groups.members.update', [$this->group, $this->plainMember]), [
        'name' => 'Renamed Person', 'phone_number' => '0711 222 333', 'membership_type_id' => memberTypeId($this),
    ])->assertOk()->assertJsonPath('data.name', 'Renamed Person');

    $linkedPhone = $this->plainMember->fresh()->phone_number;

    expect($linkedPhone)->not->toBe('0711222333');

    $unlinked = $this->group->groupMembers()->create([
        'name' => 'No Account', 'phone_number' => '0700000001', 'membership_type_id' => memberTypeId($this), 'member_number' => 'X-900',
        'joined_at' => now()->toDateString(), 'membership_status' => 'active',
    ]);

    $this->actingAs($this->chair)->putJson(route('groups.members.update', [$this->group, $unlinked]), [
        'name' => 'No Account', 'phone_number' => '0711 222 333', 'membership_type_id' => memberTypeId($this),
    ])->assertOk();

    expect($unlinked->fresh()->phone_number)->toBe('0711222333');

    $this->actingAs($this->plain)->putJson(route('groups.members.update', [$this->group, $this->friendMember]), [
        'name' => 'Nope', 'membership_type_id' => memberTypeId($this),
    ])->assertForbidden();
});

test('a member can be deactivated and activated again', function () {
    $url = route('groups.members.status', [$this->group, $this->plainMember]);

    $this->actingAs($this->chair)->patchJson($url, ['status' => 'suspended'])->assertOk()->assertJsonPath('data.membership_status', 'suspended');
    $this->actingAs($this->chair)->patchJson($url, ['status' => 'active'])->assertOk()->assertJsonPath('data.membership_status', 'active');
    $this->actingAs($this->plain)->patchJson(route('groups.members.status', [$this->group, $this->friendMember]), ['status' => 'suspended'])->assertForbidden();
});

test('a member without history can be deleted together with their roles', function () {
    $this->actingAs($this->chair)->deleteJson(route('groups.members.destroy', [$this->group, $this->plainMember]))
        ->assertOk()->assertJsonPath('message', 'Member deleted.');

    $this->assertDatabaseMissing('group_members', ['id' => $this->plainMember->id]);
    $this->assertDatabaseMissing('group_member_roles', ['group_member_id' => $this->plainMember->id]);
});

test('a member with records cannot be deleted', function () {
    $this->group->fineTypes()->create(['name' => 'Absent', 'code' => 'ABSENT', 'default_amount' => 1, 'calculation_type' => 'fixed', 'status' => 'active']);
    $this->actingAs($this->chair)->postJson(route('member-fines.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'fine_type_id' => $this->group->fineTypes()->value('id'),
        'reason' => 'Late', 'fine_date' => now()->toDateString(), 'amount' => 1000,
    ])->assertCreated();

    $this->actingAs($this->chair)->deleteJson(route('groups.members.destroy', [$this->group, $this->plainMember]))
        ->assertUnprocessable()->assertJsonValidationErrors('member');

    $this->assertDatabaseHas('group_members', ['id' => $this->plainMember->id]);
});

test('you cannot delete yourself or the only chairperson, and members cannot delete anyone', function () {
    $this->actingAs($this->chair)->deleteJson(route('groups.members.destroy', [$this->group, $this->chairMember]))->assertUnprocessable();
    $this->actingAs($this->plain)->deleteJson(route('groups.members.destroy', [$this->group, $this->friendMember]))->assertForbidden();
    $this->actingAs($this->outsider)->deleteJson(route('groups.members.destroy', [$this->group, $this->friendMember]))->assertForbidden();
});

test('a member of another group cannot be reached through this group', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreign = addMemberTo($other, User::factory()->create());

    $this->actingAs($this->chair)->deleteJson(route('groups.members.destroy', [$this->group, $foreign]))->assertNotFound();
});
