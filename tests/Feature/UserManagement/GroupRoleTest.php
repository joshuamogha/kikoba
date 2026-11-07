<?php

use App\Models\UserManagement\GroupMemberRole;
use App\Models\UserManagement\GroupRole;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->secretary = User::factory()->create();
    $this->plain = User::factory()->create();

    $this->secretaryMember = addMemberTo($this->group, $this->secretary, ['Secretary']);
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->chairMember = $this->group->groupMembers()->where('user_id', $this->chair->id)->firstOrFail();
});

test('the roles page lists system roles and only this group\'s custom roles', function () {
    $this->group->groupRoles()->create(['name' => 'Auditor', 'is_system_role' => false, 'status' => 'active']);
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $other->groupRoles()->create(['name' => 'Patron', 'is_system_role' => false, 'status' => 'active']);

    $this->actingAs($this->plain)->get(route('groups.roles.index', $this->group))->assertOk()->assertSee('<group-roles', false);

    $names = collect($this->actingAs($this->plain)->getJson(route('groups.roles.index', $this->group))
        ->assertOk()->json('data'))->pluck('name');

    expect($names->all())->toBe(['Chairperson', 'Vice Chairperson', 'Secretary', 'Treasurer', 'Member', 'Auditor']);
});

test('role holder counts are scoped to the group', function () {
    $roles = collect($this->actingAs($this->chair)->getJson(route('groups.roles.index', $this->group))->json('data'))->keyBy('name');

    expect($roles['Chairperson']['members_count'])->toBe(1)
        ->and($roles['Secretary']['members_count'])->toBe(1)
        ->and($roles['Member']['members_count'])->toBe(3)
        ->and($roles['Treasurer']['members_count'])->toBe(0);
});

test('leaders can create custom roles and plain members cannot', function () {
    $this->actingAs($this->secretary)->postJson(route('groups.roles.store', $this->group), ['name' => 'Auditor', 'description' => 'Checks the books'])
        ->assertCreated()->assertJsonPath('data.name', 'Auditor')->assertJsonPath('data.is_system_role', false);

    $this->actingAs($this->plain)->postJson(route('groups.roles.store', $this->group), ['name' => 'Spy'])->assertForbidden();
});

test('custom role names cannot clash with system roles or each other but may repeat across groups', function () {
    $this->actingAs($this->chair)->postJson(route('groups.roles.store', $this->group), ['name' => 'chairperson'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->actingAs($this->chair)->postJson(route('groups.roles.store', $this->group), ['name' => 'Auditor'])->assertCreated();
    $this->actingAs($this->chair)->postJson(route('groups.roles.store', $this->group), ['name' => 'Auditor'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $otherChair = User::factory()->create();
    $other = createGroupFor($otherChair, ['name' => 'Other']);
    $this->actingAs($otherChair)->postJson(route('groups.roles.store', $other), ['name' => 'Auditor'])->assertCreated();
});

test('custom roles can be edited and deactivated but system roles cannot', function () {
    $role = $this->group->groupRoles()->create(['name' => 'Auditor', 'is_system_role' => false, 'status' => 'active']);

    $this->actingAs($this->chair)->putJson(route('groups.roles.update', [$this->group, $role]), ['name' => 'Internal Auditor', 'status' => 'inactive'])
        ->assertOk()->assertJsonPath('data.status', 'inactive');

    $this->actingAs($this->chair)->putJson(route('groups.roles.update', [$this->group, systemRoleId('Treasurer')]), ['name' => 'X', 'status' => 'active'])
        ->assertNotFound();
});

test('a role of another group cannot be touched', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreign = $other->groupRoles()->create(['name' => 'Patron', 'is_system_role' => false, 'status' => 'active']);

    $this->actingAs($this->chair)->deleteJson(route('groups.roles.destroy', [$this->group, $foreign]))->assertNotFound();
});

test('an unused custom role is deleted but an assigned one is kept', function () {
    $unused = $this->group->groupRoles()->create(['name' => 'Spare', 'is_system_role' => false, 'status' => 'active']);
    $used = $this->group->groupRoles()->create(['name' => 'Auditor', 'is_system_role' => false, 'status' => 'active']);

    $this->actingAs($this->chair)->postJson(route('groups.members.roles.store', [$this->group, $this->plainMember]), ['group_role_id' => $used->id])->assertCreated();

    $this->actingAs($this->chair)->deleteJson(route('groups.roles.destroy', [$this->group, $unused]))->assertOk();
    $this->actingAs($this->chair)->deleteJson(route('groups.roles.destroy', [$this->group, $used]))->assertUnprocessable();

    $this->assertModelMissing($unused);
    $this->assertModelExists($used);
});

test('leaders can assign roles and plain members cannot', function () {
    $this->actingAs($this->secretary)
        ->postJson(route('groups.members.roles.store', [$this->group, $this->plainMember]), ['group_role_id' => systemRoleId('Treasurer')])
        ->assertCreated();

    expect($this->plainMember->groupMemberRoles()->active()->where('group_role_id', systemRoleId('Treasurer'))->exists())->toBeTrue();

    $this->actingAs($this->plain)
        ->postJson(route('groups.members.roles.store', [$this->group, $this->plainMember]), ['group_role_id' => systemRoleId('Secretary')])
        ->assertForbidden();
});

test('role assignment rejects duplicates, the Member role, inactive roles, foreign roles and non-active members', function () {
    $inactive = $this->group->groupRoles()->create(['name' => 'Old', 'is_system_role' => false, 'status' => 'inactive']);
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreign = $other->groupRoles()->create(['name' => 'Patron', 'is_system_role' => false, 'status' => 'active']);
    $url = route('groups.members.roles.store', [$this->group, $this->secretaryMember]);

    foreach ([systemRoleId('Secretary'), systemRoleId('Member'), $inactive->id, $foreign->id] as $roleId) {
        $this->actingAs($this->chair)->postJson($url, ['group_role_id' => $roleId])
            ->assertUnprocessable()->assertJsonValidationErrors('group_role_id');
    }

    $this->plainMember->update(['membership_status' => 'suspended']);
    $this->actingAs($this->chair)
        ->postJson(route('groups.members.roles.store', [$this->group, $this->plainMember]), ['group_role_id' => systemRoleId('Treasurer')])
        ->assertUnprocessable();
});

test('giving someone Chairperson hands the role over', function () {
    $this->actingAs($this->chair)
        ->postJson(route('groups.members.roles.store', [$this->group, $this->plainMember]), ['group_role_id' => systemRoleId('Chairperson')])
        ->assertCreated()
        ->assertJsonPath('message', 'Role assigned. The previous Chairperson no longer holds it.');

    expect($this->plain->leadsGroup($this->group))->toBeTrue()
        ->and($this->chair->leadsGroup($this->group))->toBeFalse()
        ->and($this->group->activeRoleAssignments('Chairperson')->count())->toBe(1)
        ->and(GroupMemberRole::query()->where('group_member_id', $this->chairMember->id)->where('status', 'ended')->count())->toBe(1);

    $this->actingAs($this->chair)
        ->postJson(route('groups.members.roles.store', [$this->group, $this->plainMember]), ['group_role_id' => systemRoleId('Treasurer')])
        ->assertForbidden();
});

test('ending a role keeps the history', function () {
    $assignment = $this->secretaryMember->groupMemberRoles()->active()->where('group_role_id', systemRoleId('Secretary'))->firstOrFail();

    $this->actingAs($this->chair)
        ->deleteJson(route('groups.members.roles.destroy', [$this->group, $this->secretaryMember, $assignment]))
        ->assertOk();

    expect($assignment->refresh()->status)->toBe('ended')
        ->and($assignment->ends_at)->not->toBeNull()
        ->and($this->secretary->leadsGroup($this->group))->toBeFalse();
});

test('the only chairperson and the Member role cannot be ended', function () {
    $chairAssignment = $this->chairMember->groupMemberRoles()->active()->where('group_role_id', systemRoleId('Chairperson'))->firstOrFail();
    $memberAssignment = $this->plainMember->groupMemberRoles()->active()->where('group_role_id', systemRoleId('Member'))->firstOrFail();

    $this->actingAs($this->chair)
        ->deleteJson(route('groups.members.roles.destroy', [$this->group, $this->chairMember, $chairAssignment]))
        ->assertUnprocessable();
    $this->actingAs($this->chair)
        ->deleteJson(route('groups.members.roles.destroy', [$this->group, $this->plainMember, $memberAssignment]))
        ->assertUnprocessable();

    expect($this->chair->leadsGroup($this->group))->toBeTrue();
});

test('an assignment of another member is not found', function () {
    $assignment = $this->secretaryMember->groupMemberRoles()->active()->firstOrFail();

    $this->actingAs($this->chair)
        ->deleteJson(route('groups.members.roles.destroy', [$this->group, $this->plainMember, $assignment]))
        ->assertNotFound();
});

test('the Vice Chairperson and Secretary can both manage but the Treasurer cannot', function (string $role, int $expected) {
    $user = User::factory()->create();
    addMemberTo($this->group, $user, [$role]);

    $this->actingAs($user)->postJson(route('groups.roles.store', $this->group), ['name' => 'Role '.$role])->assertStatus($expected);
})->with([
    'vice chairperson' => ['Vice Chairperson', 201],
    'secretary' => ['Secretary', 201],
    'treasurer' => ['Treasurer', 403],
]);

test('system roles are created when missing', function () {
    GroupMemberRole::query()->delete();
    GroupRole::query()->delete();

    createGroupFor(User::factory()->create(), ['name' => 'Fresh']);

    expect(GroupRole::query()->whereNull('group_id')->count())->toBe(5);
});
