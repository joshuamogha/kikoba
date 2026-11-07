<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);

    foreach ([[2025, 'closed'], [2026, 'open']] as [$year, $status]) {
        $this->group->financialCycles()->create([
            'contribution_frequency_id' => $this->group->contributionFrequencies()->value('id'),
            'name' => "Cycle {$year}", 'cycle_number' => $year, 'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => $status,
        ]);
    }

    $this->fund = createFundFor($this->group);
    $this->group->groupSetting()->update(['share_price' => 5000, 'maximum_shares' => null]);
});

test('shares can be filtered by member and month and the totals follow the filter', function () {
    buyShares($this, $this->plainMember, '2026-01', 2, '2026-01-10');
    buyShares($this, $this->plainMember, '2026-02', 3, '2026-02-10');
    buyShares($this, $this->friendMember, '2026-02', 1, '2026-02-12');
    $url = route('groups.shares.index', $this->group);

    $this->actingAs($this->chair)->getJson($url)->assertJsonPath('meta.total', 3)->assertJsonPath('totals.shares', 6)->assertJsonCount(3, 'filters.members');
    $this->actingAs($this->chair)->getJson($url.'?group_member_id='.$this->plainMember->id)
        ->assertJsonPath('meta.total', 2)->assertJsonPath('totals.shares', 5)->assertJsonPath('totals.amount', 25000);
    $this->actingAs($this->chair)->getJson($url.'?month=2026-02')->assertJsonPath('meta.total', 2)->assertJsonPath('totals.shares', 4);
    $this->actingAs($this->chair)->getJson($url.'?month=2026-02&group_member_id='.$this->plainMember->id)->assertJsonPath('meta.total', 1)->assertJsonPath('totals.shares', 3);
    $this->actingAs($this->chair)->getJson($url.'?month=not-a-month')->assertJsonPath('meta.total', 3);
});

test('a member cannot use the member filter to see someone else\'s shares', function () {
    buyShares($this, $this->plainMember, '2026-02', 3, '2026-02-10');
    buyShares($this, $this->friendMember, '2026-02', 1, '2026-02-12');

    $this->actingAs($this->plain)->getJson(route('groups.shares.index', $this->group).'?group_member_id='.$this->friendMember->id)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('totals.shares', 3)->assertJsonCount(0, 'filters.members');
});

test('contributions can be filtered by member, month and financial cycle', function () {
    payFor($this, $this->plainMember, '2026-02', 20000);
    payFor($this, $this->plainMember, '2026-03', 10000);
    payFor($this, $this->friendMember, '2026-02', 5000);
    payFor($this, $this->plainMember, '2025-12', 7000, '2025-12-20');
    $url = route('contributions.index', ['group_id' => $this->group->id]);

    $response = $this->actingAs($this->chair)->getJson($url)->assertJsonPath('meta.total', 4);
    $cycleIds = $this->group->financialCycles()->pluck('id', 'name');

    expect(collect($response->json('filters.cycles'))->pluck('value')->all())->toBe([$cycleIds['Cycle 2026'], $cycleIds['Cycle 2025']])
        ->and($response->json('filters.members'))->toHaveCount(3);

    $this->actingAs($this->chair)->getJson("{$url}&group_member_id={$this->plainMember->id}")->assertJsonPath('meta.total', 3);
    $this->actingAs($this->chair)->getJson("{$url}&month=2026-02")->assertJsonPath('meta.total', 2);
    $this->actingAs($this->chair)->getJson("{$url}&financial_cycle_id={$cycleIds['Cycle 2025']}")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.total_amount', 7000);
    $this->actingAs($this->chair)->getJson("{$url}&financial_cycle_id={$cycleIds['Cycle 2026']}&month=2026-02&group_member_id={$this->friendMember->id}")
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.total_amount', 5000);
});

test('both member lists show each member\'s total shares and share amount', function () {
    buyShares($this, $this->plainMember, '2026-01', 2, '2026-01-10');
    buyShares($this, $this->plainMember, '2026-02', 3, '2026-02-10');

    foreach ([route('members.index', ['group_id' => $this->group->id]), route('groups.members.index', $this->group)] as $url) {
        $rows = collect($this->actingAs($this->chair)->getJson($url)->assertOk()->json('data'))->keyBy('id');

        expect($rows[$this->plainMember->id]['shares_count'])->toBe(5)
            ->and($rows[$this->plainMember->id]['shares_amount'])->toBe(25000)
            ->and($rows[$this->friendMember->id]['shares_count'])->toBe(0)
            ->and($rows[$this->friendMember->id]['shares_amount'])->toBe(0);
    }
});

test('contributions can be filtered by fund', function () {
    $other = createFundFor($this->group, ['name' => 'Social', 'code' => 'SOC']);
    payFor($this, $this->plainMember, '2026-02', 20000);

    $id = $this->actingAs($this->chair)->postJson(route('contributions.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->friendMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => '2026-04-10', 'period' => '2026-02', 'items' => [['group_fund_id' => $other->id, 'amount' => 3000]],
    ])->assertCreated()->json('data.id');

    $url = route('contributions.index', ['group_id' => $this->group->id]);

    $response = $this->actingAs($this->chair)->getJson($url)->assertJsonPath('meta.total', 2);

    expect(collect($response->json('filters.funds'))->pluck('label')->sort()->values()->all())->toBe(['Savings', 'Social']);

    $this->actingAs($this->chair)->getJson("{$url}&group_fund_id={$other->id}")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    $this->actingAs($this->chair)->getJson("{$url}&group_fund_id={$this->fund->id}")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.funds.0.name', 'Savings');
});

test('contributions show the total number and amount for the current filters', function () {
    $social = createFundFor($this->group, ['name' => 'Social', 'code' => 'SOC']);
    payFor($this, $this->plainMember, '2026-02', 20000);
    payFor($this, $this->plainMember, '2026-03', 10000, approve: false);
    $this->actingAs($this->chair)->postJson(route('contributions.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->friendMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => '2026-04-10', 'period' => '2026-02',
        'items' => [['group_fund_id' => $this->fund->id, 'amount' => 4000], ['group_fund_id' => $social->id, 'amount' => 1000]],
    ])->assertCreated();
    $url = route('contributions.index', ['group_id' => $this->group->id]);

    $this->actingAs($this->chair)->getJson($url)->assertJsonPath('totals.count', 3)->assertJsonPath('totals.amount', 35000);
    $this->actingAs($this->chair)->getJson("{$url}&status=pending")->assertJsonPath('totals.count', 2)->assertJsonPath('totals.amount', 15000);
    $this->actingAs($this->chair)->getJson("{$url}&status=approved")->assertJsonPath('totals.count', 1)->assertJsonPath('totals.amount', 20000);
    $this->actingAs($this->chair)->getJson("{$url}&month=2026-02")->assertJsonPath('totals.count', 2)->assertJsonPath('totals.amount', 25000);
    $this->actingAs($this->chair)->getJson("{$url}&group_fund_id={$social->id}")->assertJsonPath('totals.count', 1)->assertJsonPath('totals.amount', 1000);
    $this->actingAs($this->plain)->getJson($url)->assertJsonPath('totals.count', 2)->assertJsonPath('totals.amount', 30000);
});
