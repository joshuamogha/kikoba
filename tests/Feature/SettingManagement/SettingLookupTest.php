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

dataset('setting lookups', [
    'fund types' => ['fund-types', true],
    'contribution frequencies' => ['contribution-frequencies', false],
    'loan interest methods' => ['loan-interest-methods', true],
    'membership types' => ['membership-types', false],
    'penalty types' => ['penalty-types', false],
]);

test('each setting lookup lists only the chosen group', function (string $slug) {
    $own = $this->actingAs($this->plain)->getJson(route("$slug.index", ['group_id' => $this->group->id]))->assertOk()->assertJsonPath('can_manage', false);
    $foreign = $this->actingAs($this->other->creator)->getJson(route("$slug.index", ['group_id' => $this->other->id]))->assertOk();

    expect($own->json('meta.total'))->toBeGreaterThan(0)
        ->and(collect($own->json('data'))->pluck('id')->intersect(collect($foreign->json('data'))->pluck('id')))->toBeEmpty();

    $this->actingAs($this->outsider)->getJson(route("$slug.index", ['group_id' => $this->group->id]))->assertForbidden();
})->with('setting lookups');

test('leaders add and rename lookups, plain members cannot', function (string $slug, bool $hasCode) {
    $payload = ['group_id' => $this->group->id, 'name' => 'Brand New', ...($hasCode ? ['code' => 'new'] : [])];

    $id = $this->actingAs($this->treasurer)->postJson(route("$slug.store"), $payload)->assertCreated()->json('data.id');
    $this->actingAs($this->plain)->postJson(route("$slug.store"), [...$payload, 'name' => 'Other'])->assertForbidden();
    $this->actingAs($this->treasurer)->postJson(route("$slug.store"), $payload)->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->actingAs($this->treasurer)->putJson(route("$slug.update", $id), [...$payload, 'name' => 'Renamed', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.is_active', false);
})->with('setting lookups');

test('the code of an interest method is locked because the loan schedule depends on it', function () {
    $flat = $this->group->loanInterestMethods()->where('code', 'FLAT')->firstOrFail();

    $this->actingAs($this->chair)->putJson(route('loan-interest-methods.update', $flat->id), ['group_id' => $this->group->id, 'name' => 'Flat interest', 'is_active' => true, 'code' => 'CHANGED'])
        ->assertOk()->assertJsonPath('data.name', 'Flat interest')->assertJsonPath('data.code', 'FLAT');

    $this->actingAs($this->chair)->putJson(route('loan-interest-methods.update', $flat->id), ['group_id' => $this->group->id, 'name' => 'Flat', 'is_active' => true])->assertOk();
});
