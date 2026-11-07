<?php

use App\Models\UserManagement\GroupType;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('group-types.index'))->assertRedirect(route('login'));
    $this->postJson(route('group-types.store'), ['name' => 'X'])->assertUnauthorized();
});

test('members who are not platform admins are forbidden', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('group-types.index'))->assertForbidden();
    $this->actingAs($member)->postJson(route('group-types.store'), ['name' => 'X'])->assertForbidden();
});

test('the page mounts the group types component', function () {
    $this->actingAs($this->admin)->get(route('group-types.index'))
        ->assertOk()
        ->assertSee('<group-types></group-types>', false);
});

test('group types are listed as paginated json with group counts', function () {
    $type = GroupType::factory()->create(['name' => 'VICOBA', 'display_order' => 1]);
    GroupType::factory()->create(['name' => 'SACCOS', 'display_order' => 2]);

    $this->actingAs($this->admin)->getJson(route('group-types.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'VICOBA')
        ->assertJsonPath('data.0.id', $type->id)
        ->assertJsonPath('data.0.groups_count', 0)
        ->assertJsonPath('meta.total', 2);
});

test('group types can be searched by name', function () {
    GroupType::factory()->create(['name' => 'Burial Society']);
    GroupType::factory()->create(['name' => 'Youth Group']);

    $this->actingAs($this->admin)->getJson(route('group-types.index', ['q' => 'burial']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Burial Society');
});

test('a group type can be created and is placed last', function () {
    GroupType::factory()->create(['display_order' => 4]);

    $this->actingAs($this->admin)->postJson(route('group-types.store'), ['name' => 'Upatu'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Upatu');

    $this->assertDatabaseHas('group_types', ['name' => 'Upatu', 'is_active' => true, 'display_order' => 5]);
});

test('creating validates the name', function (array $payload) {
    GroupType::factory()->create(['name' => 'VICOBA']);

    $this->actingAs($this->admin)->postJson(route('group-types.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with([
    'missing' => [[]],
    'too long' => [['name' => str_repeat('a', 101)]],
    'duplicate' => [['name' => 'VICOBA']],
]);

test('a group type can be renamed and keeps its own name valid', function () {
    $type = GroupType::factory()->create(['name' => 'VICOBA']);

    $this->actingAs($this->admin)->putJson(route('group-types.update', $type), ['name' => 'VICOBA'])->assertOk();
    $this->actingAs($this->admin)->putJson(route('group-types.update', $type), ['name' => 'Village Bank'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Village Bank');

    expect($type->refresh()->name)->toBe('Village Bank');
});

test('renaming to another type\'s name is rejected', function () {
    GroupType::factory()->create(['name' => 'SACCOS']);
    $type = GroupType::factory()->create(['name' => 'VICOBA']);

    $this->actingAs($this->admin)->putJson(route('group-types.update', $type), ['name' => 'SACCOS'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('a group type can be activated and deactivated', function () {
    $type = GroupType::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)->postJson(route('group-types.activate', $type))
        ->assertOk()->assertJsonPath('message', 'Group type deactivated.');
    expect($type->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->postJson(route('group-types.activate', $type))
        ->assertOk()->assertJsonPath('message', 'Group type activated.');
    expect($type->refresh()->is_active)->toBeTrue();
});

test('an unused group type can be deleted', function () {
    $type = GroupType::factory()->create();

    $this->actingAs($this->admin)->deleteJson(route('group-types.destroy', $type))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertModelMissing($type);
});

test('a group type used by a group cannot be deleted', function () {
    $this->seed();
    $type = GroupType::query()->where('name', 'VICOBA')->firstOrFail();

    $this->actingAs(User::query()->where('email', 'admin@kibubu.co.tz')->firstOrFail())
        ->deleteJson(route('group-types.destroy', $type))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    $this->assertModelExists($type);
});
