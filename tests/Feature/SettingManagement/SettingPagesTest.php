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

dataset('setting pages', [
    'general' => ['general', ['group-settings', 'membership-types']],
    'finance' => ['finance', ['fund-types', 'contribution-frequencies', 'loan-interest-methods', 'penalty-types']],
    'fines' => ['fines', ['fine-types']],
]);

test('each setting page renders its tabs', function (string $page, array $tabKeys) {
    $response = $this->actingAs($this->plain)->get(route('setting-pages.show', $page))->assertOk()->assertSee('<setting-tabs', false);

    foreach ($tabKeys as $key) {
        $response->assertSee($key);
    }
})->with('setting pages');

test('an unknown setting page is not found', function () {
    $this->actingAs($this->plain)->get('/setting-management/unknown')->assertNotFound();
});

test('the group select offers only the viewer\'s groups and says where they can manage settings', function () {
    $response = $this->actingAs($this->plain)->get(route('setting-pages.show', 'general'));
    $groups = $response->viewData('groups');

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['value'])->toBe($this->group->id)
        ->and($groups[0]['can_manage'])->toBeFalse();

    expect($this->actingAs($this->chair)->get(route('setting-pages.show', 'general'))->viewData('groups')[0]['can_manage'])->toBeTrue();
});

test('guests are sent to sign in', function () {
    $this->get(route('setting-pages.show', 'general'))->assertRedirect();
});
