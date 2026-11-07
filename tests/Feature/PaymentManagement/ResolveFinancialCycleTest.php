<?php

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
});

function makeCycle($test, string $name, string $start, string $end, string $status)
{
    return $test->group->financialCycles()->create([
        'contribution_frequency_id' => $test->group->contributionFrequencies()->value('id'),
        'name' => $name, 'cycle_number' => $test->group->financialCycles()->max('cycle_number') + 1,
        'start_date' => $start, 'end_date' => $end, 'status' => $status,
    ]);
}

function resolveCycleFor($test, string $date)
{
    return app(ResolveFinancialCycle::class)->handle($test->group, Carbon::parse($date), $test->chair->id);
}

test('a cycle that runs from one year into the next is used for months on both sides of the year end', function () {
    $cycle = makeCycle($this, 'FY 2025/26', '2025-12-01', '2026-11-30', 'open');

    expect(resolveCycleFor($this, '2025-12-15')->id)->toBe($cycle->id)
        ->and(resolveCycleFor($this, '2026-01-10')->id)->toBe($cycle->id)
        ->and(resolveCycleFor($this, '2026-11-30')->id)->toBe($cycle->id)
        ->and($this->group->financialCycles()->count())->toBe(1);
});

test('an open cycle wins over a draft, a draft is opened, and a closed cycle still takes old data', function () {
    $closed = makeCycle($this, 'Old', '2024-01-01', '2024-12-31', 'closed');
    $draft = makeCycle($this, 'Draft year', '2025-01-01', '2025-12-31', 'draft');

    expect(resolveCycleFor($this, '2024-06-01')->id)->toBe($closed->id)
        ->and($closed->fresh()->status)->toBe('closed')
        ->and(resolveCycleFor($this, '2025-06-01')->id)->toBe($draft->id)
        ->and($draft->fresh()->status)->toBe('open')
        ->and($draft->fresh()->opened_by)->toBe($this->chair->id);
});

test('without a cycle a calendar-year cycle is opened, shortened so it never overlaps a neighbour', function () {
    makeCycle($this, 'Ends in March', '2025-04-01', '2026-03-31', 'open');
    makeCycle($this, 'Starts in October', '2026-10-01', '2027-09-30', 'open');

    $cycle = resolveCycleFor($this, '2026-06-15');

    expect($cycle->start_date->toDateString())->toBe('2026-04-01')
        ->and($cycle->end_date->toDateString())->toBe('2026-09-30')
        ->and($cycle->status)->toBe('open');

    $alone = createGroupFor(User::factory()->create(), ['name' => 'Alone']);
    $fresh = app(ResolveFinancialCycle::class)->handle($alone, Carbon::parse('2026-06-15'), $alone->created_by);

    expect($fresh->start_date->toDateString())->toBe('2026-01-01')->and($fresh->end_date->toDateString())->toBe('2026-12-31');
});

test('shares for both years of a cross-year cycle land in one calendar of twelve months', function () {
    $this->group->groupSetting()->update(['share_price' => 5000, 'maximum_shares' => null]);
    $cycle = makeCycle($this, 'FY 2025/26', '2025-12-01', '2026-11-30', 'draft');
    $member = addMemberTo($this->group, User::factory()->create());
    $member->update(['joined_at' => '2025-01-01']);

    buyShares($this, $member, '2025-12', 2, '2025-12-10');
    buyShares($this, $member, '2026-11', 3, '2026-11-05');

    expect(MemberShareTransaction::query()->pluck('financial_cycle_id')->unique()->all())->toBe([$cycle->id]);

    $response = calendarFor($this, query: ['type' => 'shares', 'financial_cycle_id' => $cycle->id])->assertOk()
        ->assertJsonCount(12, 'data.months')
        ->assertJsonPath('data.months.0.key', '2025-12')
        ->assertJsonPath('data.months.11.key', '2026-11')
        ->assertJsonPath('data.totals.2025-12', 10000)
        ->assertJsonPath('data.totals.2026-11', 15000);

    expect($response->json('data.grand_total'))->toBe(25000);
});
