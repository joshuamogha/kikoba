<?php

use App\Models\UserManagement\Relation;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('relations.index'))->assertRedirect(route('login'));
    $this->postJson(route('relations.store'), ['name' => 'Spouse'])->assertUnauthorized();
});

test('users who are not platform admins are forbidden', function () {
    $user = User::factory()->create();
    $relation = Relation::factory()->create();

    $this->actingAs($user)->get(route('relations.index'))->assertForbidden();
    $this->actingAs($user)->postJson(route('relations.store'), ['name' => 'Spouse'])->assertForbidden();
    $this->actingAs($user)->putJson(route('relations.update', $relation), ['name' => 'Kin'])->assertForbidden();
    $this->actingAs($user)->deleteJson(route('relations.destroy', $relation))->assertForbidden();
});

test('the page mounts the relations component', function () {
    $this->actingAs($this->admin)->get(route('relations.index'))
        ->assertOk()
        ->assertSee('<relations></relations>', false);
});

test('relations are listed as paginated json and can be searched', function () {
    Relation::factory()->create(['name' => 'Spouse', 'display_order' => 1]);
    Relation::factory()->create(['name' => 'Sibling', 'display_order' => 2]);
    Relation::factory()->count(15)->create();

    $this->actingAs($this->admin)->getJson(route('relations.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Spouse')
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 17);

    $this->actingAs($this->admin)->getJson(route('relations.index', ['q' => 'Sibl']))
        ->assertJsonPath('meta.total', 1);
});

test('a relation is created last in the order and the name must be unique', function () {
    Relation::factory()->create(['display_order' => 4]);

    $this->actingAs($this->admin)->postJson(route('relations.store'), ['name' => 'Parent', 'description' => 'Mother or father'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Parent');

    $this->assertDatabaseHas('relations', ['name' => 'Parent', 'display_order' => 5, 'is_active' => true]);

    $this->actingAs($this->admin)->postJson(route('relations.store'), ['name' => 'Parent'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->actingAs($this->admin)->postJson(route('relations.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

test('a relation can be updated keeping its own name', function () {
    $relation = Relation::factory()->create(['name' => 'Child']);
    Relation::factory()->create(['name' => 'Cousin']);

    $this->actingAs($this->admin)->putJson(route('relations.update', $relation), ['name' => 'Child', 'description' => 'Son or daughter'])
        ->assertOk();
    expect($relation->fresh()->description)->toBe('Son or daughter');

    $this->actingAs($this->admin)->putJson(route('relations.update', $relation), ['name' => 'Cousin'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

test('a relation can be deactivated, activated and deleted', function () {
    $relation = Relation::factory()->create();

    $this->actingAs($this->admin)->postJson(route('relations.activate', $relation))->assertOk();
    expect($relation->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->postJson(route('relations.activate', $relation))->assertOk();
    expect($relation->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->admin)->deleteJson(route('relations.destroy', $relation))->assertOk();
    $this->assertModelMissing($relation);
});

test('relations sit under platform admin and are gone from user management', function () {
    $menus = config('sidebar');

    expect(collect($menus['platform']['items'])->pluck('route'))->toContain('relations.index')
        ->and(collect($menus['menus'][0]['items'])->pluck('label'))->not->toContain('Relations');
});
