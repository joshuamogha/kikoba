<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $this->group->ensureGroupSetting()->update(['share_price' => 5000]);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
});

/**
 * @return array<string, mixed>
 */
function sharePayload($member, array $overrides = []): array
{
    return [
        'group_member_id' => $member->id,
        'number_of_shares' => 4,
        'transaction_date' => now()->toDateString(),
        ...$overrides,
    ];
}

test('leaders and the treasurer can record shares for a member', function () {
    $this->actingAs($this->treasurer)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember))
        ->assertCreated()
        ->assertJsonPath('data.shares', 4)
        ->assertJsonPath('data.total_amount', 20000)
        ->assertJsonPath('data.status', 'approved');

    $transaction = $this->group->memberShareTransactions()->firstOrFail();

    expect($transaction->transaction_number)->toBe(sprintf('SHR-%d-%06d', now()->year, $transaction->id))
        ->and($transaction->financialCycle->status)->toBe('open')
        ->and($transaction->approved_by)->toBe($this->treasurer->id);
});

test('recording a second purchase reuses the open cycle', function () {
    foreach ([1, 2] as $_) {
        $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember))->assertCreated();
    }

    expect($this->group->financialCycles()->count())->toBe(1);
});

test('plain members cannot record shares but can see their own', function () {
    $this->actingAs($this->plain)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember))->assertForbidden();

    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->group->groupMembers()->where('user_id', $this->chair->id)->first()))->assertCreated();

    $this->actingAs($this->plain)->get(route('groups.shares.index', $this->group))->assertOk()->assertSee('<group-shares', false);

    $response = $this->actingAs($this->plain)->getJson(route('groups.shares.index', $this->group))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('meta_options'))->toBeNull();
});

test('members of another group and bad numbers are rejected', function () {
    $otherMember = $createdOther = addMemberTo(createGroupFor(User::factory()->create(), ['name' => 'Other']), User::factory()->create());

    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($otherMember, ['number_of_shares' => 0, 'transaction_date' => now()->addDay()->toDateString()]))
        ->assertUnprocessable()->assertJsonValidationErrors(['group_member_id', 'number_of_shares', 'transaction_date']);
});

test('the price always comes from the group settings', function () {
    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember, ['price_per_share' => 1]))
        ->assertCreated()->assertJsonPath('data.price_per_share', 5000)->assertJsonPath('data.total_amount', 20000);
});

test('shares cannot be recorded until the group has a share price', function () {
    $this->group->groupSetting()->update(['share_price' => 0]);

    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember))
        ->assertUnprocessable()->assertJsonValidationErrors('number_of_shares');
});

test('a member\'s shares for the month must stay within the group minimum and maximum', function () {
    $this->group->groupSetting()->update(['minimum_shares' => 2, 'maximum_shares' => 10]);
    $url = route('groups.shares.store', $this->group);

    $this->actingAs($this->chair)->postJson($url, sharePayload($this->plainMember, ['number_of_shares' => 1]))->assertUnprocessable();
    $this->actingAs($this->chair)->postJson($url, sharePayload($this->plainMember, ['number_of_shares' => 8]))->assertCreated();
    $this->actingAs($this->chair)->postJson($url, sharePayload($this->plainMember, ['number_of_shares' => 3]))
        ->assertUnprocessable()->assertJsonValidationErrors('number_of_shares');
    $this->actingAs($this->chair)->postJson($url, sharePayload($this->plainMember, ['number_of_shares' => 2]))->assertCreated();

    $this->actingAs($this->chair)->postJson($url, sharePayload($this->plainMember, ['number_of_shares' => 9, 'period' => now()->subMonth()->format('Y-m')]))->assertCreated();
});

test('leaders get the member list and default price', function () {
    $this->actingAs($this->chair)->getJson(route('groups.shares.index', $this->group))
        ->assertOk()
        ->assertJsonCount(3, 'meta_options.members')
        ->assertJsonPath('meta_options.price_per_share', 5000);
});

test('the shares page reports the total shares and amount, scoped to what the viewer can see', function () {
    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->plainMember, ['number_of_shares' => 3]))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), sharePayload($this->group->groupMembers()->where('user_id', $this->chair->id)->first(), ['number_of_shares' => 2]))->assertCreated();

    $this->actingAs($this->chair)->getJson(route('groups.shares.index', $this->group))
        ->assertJsonPath('totals.shares', 5)->assertJsonPath('totals.amount', 25000);
    $this->actingAs($this->plain)->getJson(route('groups.shares.index', $this->group))
        ->assertJsonPath('totals.shares', 3)->assertJsonPath('totals.amount', 15000);
});
