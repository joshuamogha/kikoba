<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-04-15 10:00:00');

    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->chairMember = $this->group->groupMembers()->where('user_id', $this->chair->id)->firstOrFail();
    $this->chairMember->update(['joined_at' => '2026-01-01']);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->plainMember->update(['joined_at' => '2026-02-01']);
    $this->outsider = User::factory()->create();

    $this->cycle = $this->group->financialCycles()->create([
        'contribution_frequency_id' => $this->group->contributionFrequencies()->value('id'),
        'name' => 'Cycle 2026', 'cycle_number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
    ]);
    $this->fund = createFundFor($this->group, ['is_mandatory' => true, 'minimum_contribution' => 20000]);
    $this->group->groupSetting()->update(['share_price' => 5000]);
});

test('the calendar shows paid, short, missing, upcoming and not-a-member months', function () {
    payFor($this, $this->plainMember, '2026-02', 20000);
    payFor($this, $this->plainMember, '2026-03', 10000);

    $response = calendarFor($this)->assertOk()
        ->assertJsonPath('data.cycle.id', $this->cycle->id)
        ->assertJsonPath('data.expected', 20000)
        ->assertJsonCount(12, 'data.months')
        ->assertJsonPath('data.months.0.key', '2026-01');

    $rows = collect($response->json('data.rows'))->keyBy('member_id');
    $plain = $rows[$this->plainMember->id];
    $chair = $rows[$this->chairMember->id];

    expect(collect($plain['cells'])->map->status->only(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'])->all())
        ->toBe(['2026-01' => 'n/a', '2026-02' => 'paid', '2026-03' => 'short', '2026-04' => 'upcoming', '2026-05' => 'upcoming'])
        ->and($plain['total'])->toBe(30000)
        ->and([$plain['months_paid'], $plain['months_short'], $plain['months_missing']])->toBe([1, 1, 0])
        ->and(collect($chair['cells'])->map->status->only(['2026-01', '2026-02', '2026-03', '2026-04'])->all())
        ->toBe(['2026-01' => 'missing', '2026-02' => 'missing', '2026-03' => 'missing', '2026-04' => 'upcoming'])
        ->and($chair['months_missing'])->toBe(3)
        ->and($response->json('data.totals.2026-02'))->toBe(20000)
        ->and($response->json('data.grand_total'))->toBe(30000);
});

test('payments for the same month add up and pending ones do not count', function () {
    payFor($this, $this->plainMember, '2026-03', 12000);
    payFor($this, $this->plainMember, '2026-03', 8000);
    payFor($this, $this->plainMember, '2026-03', 5000, approve: false);

    $cell = collect(calendarFor($this)->json('data.rows'))->firstWhere('member_id', $this->plainMember->id)['cells']['2026-03'];

    expect($cell['amount'])->toBe(20000)->and($cell['status'])->toBe('paid');
});

test('a month paid late is shown under the month it is for', function () {
    payFor($this, $this->plainMember, '2026-02', 20000, '2026-04-01');

    $cells = collect(calendarFor($this)->json('data.rows'))->firstWhere('member_id', $this->plainMember->id)['cells'];

    expect($cells['2026-02']['status'])->toBe('paid')->and($cells['2026-04']['amount'])->toBe(0);
});

test('the shares calendar counts any purchase and shows the number of shares', function () {
    buyShares($this, $this->plainMember, '2026-02', 4);

    $response = calendarFor($this, query: ['type' => 'shares'])->assertOk()->assertJsonPath('data.type', 'shares')->assertJsonPath('data.expected', 0);
    $cell = collect($response->json('data.rows'))->firstWhere('member_id', $this->plainMember->id)['cells']['2026-02'];

    expect($cell)->toMatchArray(['amount' => 20000, 'quantity' => 4, 'status' => 'paid']);
});

test('members who left show not-a-member after leaving, and members who joined later are only in cycles they were part of', function () {
    payFor($this, $this->plainMember, '2026-02', 20000);
    $this->plainMember->update(['membership_status' => 'exited', 'left_at' => '2026-02-10']);

    $cells = collect(calendarFor($this)->json('data.rows'))->firstWhere('member_id', $this->plainMember->id)['cells'];

    expect($cells['2026-02']['status'])->toBe('paid')->and($cells['2026-03']['status'])->toBe('n/a');

    $this->plainMember->update(['joined_at' => '2027-03-01', 'left_at' => null, 'membership_status' => 'active']);

    expect(collect(calendarFor($this)->json('data.rows'))->pluck('member_id')->all())->toBe([$this->chairMember->id]);
});

test('members see only their own row and cannot ask for another member', function () {
    payFor($this, $this->plainMember, '2026-02', 20000);

    $rows = calendarFor($this, $this->plain)->assertOk()->assertJsonPath('can_manage', false)->json('data.rows');

    expect($rows)->toHaveCount(1)->and($rows[0]['member_id'])->toBe($this->plainMember->id);

    calendarFor($this, $this->plain, ['group_member_id' => $this->plainMember->id])->assertOk();
    calendarFor($this, $this->plain, ['group_member_id' => $this->chairMember->id])->assertForbidden();
    calendarFor($this, $this->chair, ['group_member_id' => $this->plainMember->id])->assertJsonCount(1, 'data.rows');
});

test('outsiders are refused and a cycle of another group is not found', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreignCycle = $other->financialCycles()->create([
        'contribution_frequency_id' => $other->contributionFrequencies()->value('id'), 'name' => 'Foreign', 'cycle_number' => 1,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
    ]);

    calendarFor($this, $this->outsider)->assertForbidden();
    calendarFor($this, query: ['financial_cycle_id' => $foreignCycle->id])->assertNotFound();
});

test('the cycle covering today is the default and others can be chosen', function () {
    $old = $this->group->financialCycles()->create([
        'contribution_frequency_id' => $this->group->contributionFrequencies()->value('id'), 'name' => 'Cycle 2025', 'cycle_number' => 0,
        'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'closed',
    ]);

    calendarFor($this)->assertJsonPath('data.cycle.id', $this->cycle->id)->assertJsonCount(2, 'cycles');
    calendarFor($this, query: ['financial_cycle_id' => $old->id])->assertJsonPath('data.cycle.name', 'Cycle 2025')->assertJsonPath('data.months.0.key', '2025-01');
});

test('a group with no cycle yet gets an empty calendar and the page renders', function () {
    $empty = createGroupFor(User::factory()->create(), ['name' => 'Fresh']);

    $this->actingAs($empty->creator)->getJson(route('contribution-calendar.index', ['group_id' => $empty->id]))
        ->assertOk()->assertJsonPath('data', null)->assertJsonPath('cycles', []);

    $this->actingAs($this->chair)->get(route('contribution-calendar.index'))->assertOk()->assertSee('<contribution-calendar', false);
    $this->actingAs($this->chair)->get(route('dashboard'))->assertSee(route('contribution-calendar.index'), false);
});

test('the member profile exposes the same grid for one member', function () {
    payFor($this, $this->plainMember, '2026-02', 20000);

    $this->actingAs($this->chair)->get(route('members.show', $this->plainMember))->assertOk();
    calendarFor($this, query: ['group_member_id' => $this->plainMember->id])->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.cells.2026-02.status', 'paid');
});
