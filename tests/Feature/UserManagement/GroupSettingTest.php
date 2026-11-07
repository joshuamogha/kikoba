<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    addMemberTo($this->group, $this->plain);
});

/**
 * @return array<string, mixed>
 */
function validSettings($group, array $overrides = []): array
{
    return [
        'contribution_frequency_id' => $group->contributionFrequencies()->orderBy('id')->skip(1)->value('id'),
        'loan_interest_method_id' => $group->loanInterestMethods()->value('id'),
        'penalty_type_id' => $group->penaltyTypes()->value('id'),
        'minimum_contribution' => 10000,
        'maximum_contribution' => 50000,
        'share_price' => 5000,
        'minimum_shares' => 1,
        'maximum_shares' => 20,
        'default_loan_interest_rate' => 10,
        'maximum_loan_multiplier' => 3,
        'default_loan_duration_months' => 6,
        'loan_grace_period_days' => 7,
        'late_payment_penalty_value' => 2000,
        'loan_guarantors_required' => 2,
        'withdrawal_approvals_required' => 2,
        'allow_partial_contributions' => false,
        'allow_multiple_loans' => true,
        ...$overrides,
    ];
}

test('a new group gets default settings and members can view them', function () {
    expect($this->group->groupSetting()->exists())->toBeTrue();

    $this->actingAs($this->plain)->get(route('groups.settings.show', $this->group))
        ->assertOk()->assertSee('<group-settings', false);

    $this->actingAs($this->plain)->getJson(route('groups.settings.show', $this->group))
        ->assertOk()
        ->assertJsonPath('data.currency', 'TZS')
        ->assertJsonCount(5, 'data.options.contribution_frequencies');
});

test('settings are created on demand for a group that has none', function () {
    $this->group->groupSetting()->delete();

    $this->actingAs($this->chair)->getJson(route('groups.settings.show', $this->group))->assertOk();

    expect($this->group->groupSetting()->exists())->toBeTrue();
});

test('leaders can update the group settings', function () {
    $payload = validSettings($this->group);

    $this->actingAs($this->chair)->putJson(route('groups.settings.update', $this->group), $payload)
        ->assertOk()->assertJsonPath('data.share_price', '5000.00');

    $setting = $this->group->groupSetting()->first();

    expect($setting->contribution_frequency_id)->toBe($payload['contribution_frequency_id'])
        ->and($setting->allow_partial_contributions)->toBeFalse()
        ->and($setting->allow_multiple_loans)->toBeTrue()
        ->and($setting->maximum_shares)->toBe(20);
});

test('plain members cannot update the settings', function () {
    $this->actingAs($this->plain)->putJson(route('groups.settings.update', $this->group), validSettings($this->group))
        ->assertForbidden();
});

test('lookups from another group and inverted limits are rejected', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);

    $this->actingAs($this->chair)->putJson(route('groups.settings.update', $this->group), validSettings($this->group, [
        'penalty_type_id' => $other->penaltyTypes()->value('id'),
        'maximum_contribution' => 5,
        'maximum_shares' => 0,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['penalty_type_id', 'maximum_contribution', 'maximum_shares']);
});

test('the way interest is charged is a group setting, defaults to a fixed percentage of the loan, and feeds new loan products', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair);

    expect($group->ensureGroupSetting()->default_interest_period)->toBe('per_loan');

    $this->actingAs($chair)->putJson(route('groups.settings.update', $group), validSettings($group, ['default_interest_period' => 'per_month']))->assertOk()
        ->assertJsonPath('data.default_interest_period', 'per_month');
    $this->actingAs($chair)->putJson(route('groups.settings.update', $group), validSettings($group, ['default_interest_period' => 'weekly']))
        ->assertUnprocessable()->assertJsonValidationErrors('default_interest_period');

    $this->actingAs($chair)->getJson(route('loan-products.index', ['group_id' => $group->id]))->assertJsonPath('options.defaults.interest_period', 'per_month');
});
