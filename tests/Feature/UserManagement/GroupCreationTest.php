<?php

use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupRole;
use App\Models\UserManagement\GroupType;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests and unverified users cannot create groups', function () {
    $type = GroupType::factory()->create();

    $this->postJson(route('groups.store'), ['name' => 'X', 'group_type_id' => $type->id])->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson(route('groups.store'), ['name' => 'X', 'group_type_id' => $type->id])
        ->assertForbidden();
});

test('any verified user can create a group and becomes its chairperson', function () {
    $user = User::factory()->create();
    $type = GroupType::factory()->create();

    $response = $this->actingAs($user)->postJson(route('groups.store'), [
        'name' => 'Umoja wa Wanawake',
        'group_type_id' => $type->id,
        'maximum_members' => 30,
    ])->assertCreated()->assertJsonPath('success', true);

    $group = Group::query()->where('name', 'Umoja wa Wanawake')->firstOrFail();
    $response->assertJsonPath('redirect', route('groups.show', $group));

    expect($group->status)->toBe('active')
        ->and($group->currency)->toBe('TZS')
        ->and($group->created_by)->toBe($user->id);

    $member = $group->groupMembers()->firstOrFail();

    expect($member->user_id)->toBe($user->id)
        ->and($member->membership_status)->toBe('active')
        ->and($member->membershipType->name)->toBe('Founder Member')
        ->and($member->groupMemberRoles()->active()->with('groupRole')->get()->pluck('groupRole.name')->sort()->values()->all())
        ->toBe(['Chairperson', 'Member'])
        ->and($user->leadsGroup($group))->toBeTrue();
});

test('a new group gets its default lookups and the system roles exist', function () {
    $group = createGroupFor(User::factory()->create());

    expect($group->membershipTypes()->count())->toBe(3)
        ->and($group->paymentMethods()->where('code', 'MPESA')->exists())->toBeTrue()
        ->and($group->fundTypes()->count())->toBe(4)
        ->and($group->repaymentFrequencies()->where('name', 'Monthly')->value('interval_days'))->toBe(30)
        ->and(systemRoleCount())->toBe(5);
});

function systemRoleCount(): int
{
    return GroupRole::query()->whereNull('group_id')->count();
}

test('creating a group validates its input', function (array $payload, string $field) {
    $type = GroupType::factory()->create();
    $inactive = GroupType::factory()->inactive()->create();
    Group::factory()->create(['registration_number' => 'REG-1']);

    $payload = array_map(fn ($value) => $value === 'TYPE' ? $type->id : ($value === 'INACTIVE' ? $inactive->id : $value), $payload);

    $this->actingAs(User::factory()->create())
        ->postJson(route('groups.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['group_type_id' => 'TYPE'], 'name'],
    'missing type' => [['name' => 'X'], 'group_type_id'],
    'inactive type' => [['name' => 'X', 'group_type_id' => 'INACTIVE'], 'group_type_id'],
    'duplicate registration number' => [['name' => 'X', 'group_type_id' => 'TYPE', 'registration_number' => 'REG-1'], 'registration_number'],
    'maximum members too small' => [['name' => 'X', 'group_type_id' => 'TYPE', 'maximum_members' => 1], 'maximum_members'],
]);

test('users only see their own groups and platform admins can see all', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    createGroupFor($alice, ['name' => 'Alice Group']);
    createGroupFor($bob, ['name' => 'Bob Group']);

    $this->actingAs($alice)->getJson(route('groups.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Alice Group')
        ->assertJsonPath('data.0.my_roles', ['Chairperson', 'Member'])
        ->assertJsonPath('data.0.members_count', 1);

    $this->actingAs($alice)->getJson(route('groups.index', ['scope' => 'all']))->assertJsonCount(1, 'data');

    $this->actingAs(User::factory()->platformAdmin()->create())
        ->getJson(route('groups.index', ['scope' => 'all']))
        ->assertJsonCount(2, 'data');
});

test('the groups page renders for everyone', function () {
    $this->actingAs(User::factory()->create())->get(route('groups.index'))
        ->assertOk()
        ->assertSee('<groups', false)
        ->assertSee('My Groups');
});

test('only members and platform admins can open a group', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair);

    $this->actingAs($chair)->get(route('groups.show', $group))->assertOk()->assertSee($group->name);
    $this->actingAs(User::factory()->create())->get(route('groups.show', $group))->assertForbidden();
    $this->actingAs(User::factory()->platformAdmin()->create())->get(route('groups.show', $group))->assertOk();
});

test('leaders can update the group but plain members cannot', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair);
    $plain = User::factory()->create();
    addMemberTo($group, $plain);

    $this->actingAs($chair)->putJson(route('groups.update', $group), ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.name', 'Renamed');
    $this->actingAs($plain)->putJson(route('groups.update', $group), ['name' => 'Hacked'])->assertForbidden();
});

test('the maximum members cannot drop below the current member count', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair);
    addMemberTo($group, User::factory()->create());
    addMemberTo($group, User::factory()->create());

    $this->actingAs($chair)->putJson(route('groups.update', $group), ['name' => 'G', 'maximum_members' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_members');
    $this->actingAs($chair)->putJson(route('groups.update', $group), ['name' => 'G', 'maximum_members' => 5])->assertOk();
});

test('a platform admin sees only their own groups by default and everything with scope=all', function () {
    $admin = User::factory()->platformAdmin()->create();
    createGroupFor($admin, ['name' => 'Admin Own Group']);
    createGroupFor(User::factory()->create(), ['name' => 'Somebody Else Group']);

    $this->actingAs($admin)->getJson(route('groups.index'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Admin Own Group');

    $this->actingAs($admin)->getJson(route('groups.index', ['scope' => 'all']))
        ->assertJsonCount(2, 'data');
});

test('the edit group button is shown only to people who can update the group', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair);
    $plain = User::factory()->create();
    addMemberTo($group, $plain);

    $this->actingAs($chair)->get(route('groups.show', $group))->assertSee('<group-editor', false);
    $this->actingAs($plain)->get(route('groups.show', $group))->assertOk()->assertDontSee('<group-editor', false);
    $this->actingAs(User::factory()->platformAdmin()->create())->get(route('groups.show', $group))->assertSee('<group-editor', false);
});

test('editing a group validates the registration number and changes only the editable fields', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair, ['registration_number' => 'REG-100']);
    Group::factory()->create(['registration_number' => 'REG-200']);
    $statusBefore = $group->status;
    $typeBefore = $group->group_type_id;

    $this->actingAs($chair)->putJson(route('groups.update', $group), ['name' => 'Renamed', 'registration_number' => 'REG-100'])->assertOk();
    $this->actingAs($chair)->putJson(route('groups.update', $group), ['name' => 'Renamed', 'registration_number' => 'REG-200'])
        ->assertUnprocessable()->assertJsonValidationErrors('registration_number');
    $this->actingAs($chair)->putJson(route('groups.update', $group), [
        'name' => 'Renamed', 'status' => 'closed', 'group_type_id' => GroupType::factory()->create()->id,
    ])->assertOk();

    expect($group->refresh()->name)->toBe('Renamed')
        ->and($group->status)->toBe($statusBefore)
        ->and($group->group_type_id)->toBe($typeBefore);
});
