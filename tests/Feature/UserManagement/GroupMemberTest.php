<?php

use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\GroupMemberRole;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create(['name' => 'Neema Mushi']);
    $this->group = createGroupFor($this->chair);
    $this->secretary = User::factory()->create();
    $this->treasurer = User::factory()->create();
    $this->plain = User::factory()->create();
    $this->outsider = User::factory()->create(['name' => 'Baraka Mollel', 'phone_number' => '0712345678', 'email' => 'outsider@example.com']);

    addMemberTo($this->group, $this->secretary, ['Secretary']);
    addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    addMemberTo($this->group, $this->plain);

    $this->typeId = $this->group->membershipTypes()->where('name', 'Ordinary Member')->value('id');
});

function newMember(array $overrides = []): array
{
    return [
        'login' => '0712345678',
        'name' => 'Baraka Mollel',
        'membership_type_id' => test()->typeId,
        ...$overrides,
    ];
}

test('leaders can manage members but treasurers and plain members cannot', function (string $who, int $expected) {
    $this->actingAs($this->{$who})
        ->postJson(route('groups.members.store', $this->group), newMember())
        ->assertStatus($expected);
})->with([
    'chairperson' => ['chair', 201],
    'secretary' => ['secretary', 201],
    'treasurer' => ['treasurer', 403],
    'plain member' => ['plain', 403],
    'outsider' => ['outsider', 403],
]);

test('platform admins can always manage members', function () {
    $this->actingAs(User::factory()->platformAdmin()->create())
        ->postJson(route('groups.members.store', $this->group), newMember())
        ->assertCreated();
});

test('the members page and list are visible to members only', function () {
    $this->actingAs($this->plain)->get(route('groups.members.index', $this->group))
        ->assertOk()->assertSee('<group-members', false);
    $this->actingAs($this->outsider)->get(route('groups.members.index', $this->group))->assertForbidden();
    $this->actingAs($this->outsider)->getJson(route('groups.members.index', $this->group))->assertForbidden();
});

test('the member list supports search and status filter and hides emails from non-managers', function () {
    $this->actingAs($this->chair)->getJson(route('groups.members.index', $this->group))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.name', 'Neema Mushi')
        ->assertJsonPath('data.0.email', $this->chair->email)
        ->assertJsonPath('data.0.has_account', true)
        ->assertJsonPath('data.0.is_self', true);

    $this->actingAs($this->chair)->getJson(route('groups.members.index', $this->group, false).'?q='.urlencode($this->treasurer->name))
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->chair)->getJson(route('groups.members.index', $this->group, false).'?status=suspended')
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->plain)->getJson(route('groups.members.index', $this->group))
        ->assertOk()
        ->assertJsonMissingPath('data.0.email');
});

test('lookup reveals only an exact match and explains what adding would do', function () {
    $lookup = fn (string $q) => $this->actingAs($this->chair)->getJson(route('groups.members.lookup', $this->group, false).'?q='.urlencode($q));

    $lookup('0712345678')->assertOk()
        ->assertJsonPath('data.registered', true)
        ->assertJsonPath('data.name', 'Baraka Mollel')
        ->assertJsonPath('data.already_member', false);

    $lookup($this->outsider->email)->assertJsonPath('data.registered', true);

    $lookup('0799999999')->assertOk()
        ->assertJsonPath('data.registered', false)
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.phone', '0799999999');

    $lookup('0712')->assertOk()->assertJsonPath('data', null);
    $lookup('nobody@example.com')->assertOk()->assertJsonPath('data', null);
    $lookup($this->plain->email)->assertJsonPath('data.already_member', true);

    $this->actingAs($this->plain)->getJson(route('groups.members.lookup', $this->group, false).'?q=0712345678')->assertForbidden();
});

test('adding a registered user links the membership to their account', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0712 345 678', 'name' => 'Baraka M.']))
        ->assertCreated()
        ->assertJsonPath('data.has_account', true)
        ->assertJsonPath('data.name', 'Baraka M.')
        ->assertJsonPath('data.roles.0.name', 'Member');

    $member = $this->group->groupMembers()->where('user_id', $this->outsider->id)->firstOrFail();

    expect($member->phone_number)->toBe('0712345678')
        ->and($member->member_number)->toMatch('/^[A-Z]{3}-\d{3}$/')
        ->and($member->approved_by)->toBe($this->chair->id)
        ->and($this->outsider->isMemberOf($this->group))->toBeTrue();
});

test('a registered user can be added by email and keeps their account phone', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => $this->outsider->email]))
        ->assertCreated();

    expect($this->group->groupMembers()->where('user_id', $this->outsider->id)->value('phone_number'))->toBe('0712345678');
});

test('a person without an account can be added by name and phone number', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember([
        'login' => '+255 765 000 111', 'name' => 'Mama Salma',
    ]))->assertCreated()
        ->assertJsonPath('data.has_account', false)
        ->assertJsonPath('data.phone', '+255765000111')
        ->assertJsonPath('data.initials', 'MS')
        ->assertJsonPath('data.roles.0.name', 'Member');

    $member = $this->group->groupMembers()->where('phone_number', '+255765000111')->firstOrFail();

    expect($member->user_id)->toBeNull()->and($member->name)->toBe('Mama Salma');
});

test('a phone number is unique within a group but can belong to many groups', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Mama Salma']))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Someone Else']))
        ->assertUnprocessable()->assertJsonValidationErrors('login');

    $otherChair = User::factory()->create();
    $other = createGroupFor($otherChair, ['name' => 'Other']);
    $otherType = $other->membershipTypes()->first()->id;

    $this->actingAs($otherChair)->postJson(route('groups.members.store', $other), [
        'login' => '0765000111', 'name' => 'Mama Salma', 'membership_type_id' => $otherType,
    ])->assertCreated();

    expect(GroupMember::query()->where('phone_number', '0765000111')->count())->toBe(2);
});

test('adding a member is rejected for bad input', function (array $overrides, string $field) {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreignType = $other->membershipTypes()->first()->id;

    $overrides = array_map(fn ($value) => match ($value) {
        'FOREIGN' => $foreignType,
        'ALREADY' => $this->plain->phone_number,
        default => $value,
    }, $overrides);

    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'phone of an existing member' => [['login' => 'ALREADY'], 'login'],
    'unknown email' => [['login' => 'nobody@example.com'], 'login'],
    'not a phone number' => [['login' => 'abc'], 'login'],
    'missing login' => [['login' => ''], 'login'],
    'missing name' => [['name' => ''], 'name'],
    'foreign type' => [['membership_type_id' => 'FOREIGN'], 'membership_type_id'],
]);

test('an existing registered member cannot be added again by email', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => $this->plain->email]))
        ->assertUnprocessable()->assertJsonValidationErrors('login');
});

test('the maximum number of members is enforced', function () {
    $this->group->update(['maximum_members' => 4]);

    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember())
        ->assertUnprocessable()->assertJsonValidationErrors('login');
});

test('someone who left can be added back to the same membership by phone', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Mama Salma']))->assertCreated();
    $member = $this->group->groupMembers()->where('phone_number', '0765000111')->firstOrFail();
    $this->actingAs($this->chair)->patchJson(route('groups.members.status', [$this->group, $member]), ['status' => 'exited'])->assertOk();

    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Salma Juma']))->assertCreated();

    expect($this->group->groupMembers()->where('phone_number', '0765000111')->count())->toBe(1)
        ->and($member->refresh()->membership_status)->toBe('active')
        ->and($member->name)->toBe('Salma Juma')
        ->and($member->left_at)->toBeNull();
});

test('a member can be renamed and re-typed, and the phone is editable only without an account', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Mama Salma']))->assertCreated();
    $unlinked = $this->group->groupMembers()->where('phone_number', '0765000111')->firstOrFail();
    $linked = $this->group->groupMembers()->where('user_id', $this->plain->id)->firstOrFail();
    $associateId = $this->group->membershipTypes()->where('name', 'Associate Member')->value('id');

    $this->actingAs($this->chair)->putJson(route('groups.members.update', [$this->group, $unlinked]), [
        'name' => 'Salma Juma', 'phone_number' => '0765000222', 'membership_type_id' => $associateId,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Salma Juma')
        ->assertJsonPath('data.phone', '0765000222')
        ->assertJsonPath('data.membership_type.name', 'Associate Member');

    $originalPhone = $linked->phone_number;
    $this->actingAs($this->chair)->putJson(route('groups.members.update', [$this->group, $linked]), [
        'name' => 'Renamed Plain', 'phone_number' => '0700000000', 'membership_type_id' => $associateId,
    ])->assertOk()->assertJsonPath('data.phone', $originalPhone);

    $this->actingAs($this->chair)->putJson(route('groups.members.update', [$this->group, $unlinked]), [
        'name' => 'Salma Juma', 'phone_number' => $originalPhone, 'membership_type_id' => $associateId,
    ])->assertUnprocessable()->assertJsonValidationErrors('phone_number');

    $this->actingAs($this->plain)->putJson(route('groups.members.update', [$this->group, $unlinked]), [
        'name' => 'X', 'membership_type_id' => $associateId,
    ])->assertForbidden();
});

test('a leader can link a member without an account to a registered user', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Baraka Mollel']))->assertCreated();
    $member = $this->group->groupMembers()->where('phone_number', '0765000111')->firstOrFail();

    expect($this->outsider->isMemberOf($this->group))->toBeFalse();

    $this->actingAs($this->chair)->patchJson(route('groups.members.link', [$this->group, $member]), ['login' => '0712345678'])
        ->assertOk()
        ->assertJsonPath('data.has_account', true);

    expect($member->refresh()->user_id)->toBe($this->outsider->id)
        ->and($member->phone_number)->toBe('0765000111')
        ->and($this->outsider->isMemberOf($this->group))->toBeTrue();
});

test('linking is rejected when it makes no sense', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Mama Salma']))->assertCreated();
    $unlinked = $this->group->groupMembers()->where('phone_number', '0765000111')->firstOrFail();
    $linked = $this->group->groupMembers()->where('user_id', $this->plain->id)->firstOrFail();

    $this->actingAs($this->chair)->patchJson(route('groups.members.link', [$this->group, $linked]), ['login' => $this->outsider->email])
        ->assertUnprocessable()->assertJsonValidationErrors('login');
    $this->actingAs($this->chair)->patchJson(route('groups.members.link', [$this->group, $unlinked]), ['login' => '0799999999'])
        ->assertUnprocessable()->assertJsonValidationErrors('login');
    $this->actingAs($this->chair)->patchJson(route('groups.members.link', [$this->group, $unlinked]), ['login' => $this->plain->email])
        ->assertUnprocessable()->assertJsonValidationErrors('login');
    $this->actingAs($this->plain)->patchJson(route('groups.members.link', [$this->group, $unlinked]), ['login' => $this->outsider->email])
        ->assertForbidden();
});

test('members without an account cannot hold leadership roles until linked', function () {
    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), newMember(['login' => '0765000111', 'name' => 'Mama Salma']))->assertCreated();
    $member = $this->group->groupMembers()->where('phone_number', '0765000111')->firstOrFail();
    $url = route('groups.members.roles.store', [$this->group, $member]);

    foreach (['Chairperson', 'Vice Chairperson', 'Secretary'] as $role) {
        $this->actingAs($this->chair)->postJson($url, ['group_role_id' => systemRoleId($role)])
            ->assertUnprocessable()->assertJsonValidationErrors('group_role_id');
    }

    $this->actingAs($this->chair)->postJson($url, ['group_role_id' => systemRoleId('Treasurer')])->assertCreated();
    expect($this->chair->leadsGroup($this->group))->toBeTrue();

    $this->actingAs($this->chair)->patchJson(route('groups.members.link', [$this->group, $member]), ['login' => $this->outsider->email])->assertOk();
    $this->actingAs($this->chair)->postJson($url, ['group_role_id' => systemRoleId('Secretary')])->assertCreated();
});

test('members can be suspended, reinstated and removed', function () {
    $member = $this->group->groupMembers()->where('user_id', $this->plain->id)->firstOrFail();
    $url = route('groups.members.status', [$this->group, $member]);

    $this->actingAs($this->chair)->patchJson($url, ['status' => 'suspended'])->assertOk()->assertJsonPath('data.membership_status', 'suspended');
    expect($this->plain->isMemberOf($this->group))->toBeFalse();

    $this->actingAs($this->chair)->patchJson($url, ['status' => 'active'])->assertOk();
    expect($this->plain->isMemberOf($this->group))->toBeTrue();

    $this->actingAs($this->chair)->patchJson($url, ['status' => 'exited', 'reason' => 'Moved to Mwanza'])->assertOk()
        ->assertJsonPath('data.exit_reason', 'Moved to Mwanza');

    expect($member->refresh()->left_at)->not->toBeNull()
        ->and(GroupMemberRole::query()->where('group_member_id', $member->id)->active()->count())->toBe(0)
        ->and(GroupMemberRole::query()->where('group_member_id', $member->id)->count())->toBeGreaterThan(0);
});

test('the only chairperson cannot be suspended or removed', function () {
    $chairMember = $this->group->groupMembers()->where('user_id', $this->chair->id)->firstOrFail();

    $this->actingAs($this->chair)
        ->patchJson(route('groups.members.status', [$this->group, $chairMember]), ['status' => 'exited'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('a member id from another group is not found', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreignMember = $other->groupMembers()->firstOrFail();

    $this->actingAs($this->chair)
        ->patchJson(route('groups.members.status', [$this->group, $foreignMember]), ['status' => 'suspended'])
        ->assertNotFound();
});

test('a suspended leader loses management rights', function () {
    $member = $this->group->groupMembers()->where('user_id', $this->secretary->id)->firstOrFail();
    $this->actingAs($this->chair)->patchJson(route('groups.members.status', [$this->group, $member]), ['status' => 'suspended'])->assertOk();

    $this->actingAs($this->secretary)
        ->postJson(route('groups.members.store', $this->group), newMember())
        ->assertForbidden();
});
