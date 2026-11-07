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

dataset('lookups', [
    'payment methods' => ['payment-methods', true],
    'income categories' => ['income-categories', false],
    'expense categories' => ['group-expense-categories', false],
]);

test('each lookup page lists only the chosen group', function (string $slug) {
    $this->actingAs($this->plain)->get(route("$slug.index"))->assertOk()->assertSee('<lookup-manager', false);

    $own = $this->actingAs($this->plain)->getJson(route("$slug.index", ['group_id' => $this->group->id]))->assertOk()->assertJsonPath('can_manage', false);
    $foreign = $this->actingAs($this->other->creator)->getJson(route("$slug.index", ['group_id' => $this->other->id]))->assertOk();

    expect($own->json('meta.total'))->toBeGreaterThan(0)
        ->and(collect($own->json('data'))->pluck('id')->intersect(collect($foreign->json('data'))->pluck('id')))->toBeEmpty();

    $this->actingAs($this->outsider)->getJson(route("$slug.index", ['group_id' => $this->group->id]))->assertForbidden();
})->with('lookups');

test('leaders and the treasurer add lookups, plain members cannot', function (string $slug, bool $hasCode) {
    $payload = ['group_id' => $this->group->id, 'name' => 'Brand New', ...($hasCode ? ['code' => 'new'] : [])];

    $this->actingAs($this->treasurer)->postJson(route("$slug.store"), $payload)
        ->assertCreated()->assertJsonPath('data.name', 'Brand New')->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.code', $hasCode ? 'NEW' : null);

    $this->actingAs($this->plain)->postJson(route("$slug.store"), [...$payload, 'name' => 'Other'])->assertForbidden();
})->with('lookups');

test('names are unique per group', function (string $slug, bool $hasCode) {
    $existing = $this->actingAs($this->chair)->getJson(route("$slug.index", ['group_id' => $this->group->id]))->json('data.0.name');

    $this->actingAs($this->chair)->postJson(route("$slug.store"), ['group_id' => $this->group->id, 'name' => $existing, ...($hasCode ? ['code' => 'ZZ'] : [])])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
})->with('lookups');

test('a lookup can be renamed and deactivated but not across groups', function (string $slug, bool $hasCode) {
    $row = $this->actingAs($this->chair)->getJson(route("$slug.index", ['group_id' => $this->group->id]))->json('data.0');
    $payload = ['group_id' => $this->group->id, 'name' => 'Renamed', 'code' => $row['code'] ?? 'X', 'is_active' => false];

    $this->actingAs($this->chair)->putJson(route("$slug.update", $row['id']), $payload)
        ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.is_active', false);

    $foreignId = $this->actingAs($this->other->creator)->getJson(route("$slug.index", ['group_id' => $this->other->id]))->json('data.0.id');
    $this->actingAs($this->chair)->putJson(route("$slug.update", $foreignId), [...$payload, 'name' => 'Another', 'code' => 'ANOTHER'])->assertNotFound();
})->with('lookups');
