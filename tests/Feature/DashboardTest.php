<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-04-15 10:00:00');

    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->plainMember->update(['joined_at' => '2026-01-01']);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();

    $this->cycle = $this->group->financialCycles()->create([
        'contribution_frequency_id' => $this->group->contributionFrequencies()->value('id'),
        'name' => 'Cycle 2026', 'cycle_number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
    ]);
    $this->fund = createFundFor($this->group);
    $this->group->groupSetting()->update(['share_price' => 5000]);
});

function dashboardFor($test, ?User $as = null, array $query = [])
{
    return $test->actingAs($as ?? $test->chair)->getJson(route('dashboard', ['group_id' => $test->group->id, ...$query]));
}

test('the dashboard page opens for everyone and shows platform figures only to platform admins', function () {
    $this->actingAs($this->plain)->get(route('dashboard'))->assertOk()->assertSee('<dashboard', false)->assertSee(':platform="null"', false);

    $admin = User::factory()->platformAdmin()->create();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('new_users', false);
});

test('leaders see the group balance, cycle figures and charts for the chosen cycle', function () {
    payFor($this, $this->plainMember, '2026-03', 20000);
    $this->group->groupExpenses()->forceCreate([
        'financial_cycle_id' => $this->cycle->id, 'group_fund_id' => $this->fund->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'expense_number' => 'EXP-1', 'group_expense_category_id' => $this->group->groupExpenseCategories()->value('id'), 'expense_date' => '2026-04-12',
        'description' => 'Fare', 'amount' => 5000, 'payee_name' => 'Driver', 'status' => 'paid', 'paid_at' => '2026-04-12 09:00:00', 'requested_by' => $this->chair->id,
    ]);

    $response = dashboardFor($this)->assertOk()->assertJsonPath('data.scope', 'group')->assertJsonPath('cycle_id', $this->cycle->id);
    $data = $response->json('data');

    expect($data['balance']['amount'])->toBe(15000)
        ->and($data['balance']['period_in'])->toBe(20000)->and($data['balance']['period_out'])->toBe(5000)->and($data['balance']['period_net'])->toBe(15000)
        ->and($data['charts']['categories'])->toHaveCount(12)
        ->and($data['charts']['contributions'][0]['name'])->toBe('Savings')
        ->and($data['charts']['contributions'][0]['data'][2])->toBe(20000)
        ->and($data['charts']['money_flow']['in'][3])->toBe(20000)->and($data['charts']['money_flow']['out'][3])->toBe(5000)
        ->and($data['charts']['fund_split'])->toBe([['name' => 'Savings', 'amount' => 20000]])
        ->and($data['cards']['members'])->toBe(3)->and($data['cards']['contributions'])->toBe(20000)
        ->and($data['charts']['collection']['month'])->toBe('April 2026')
        ->and($data['recent'][0]['type'])->toBe('Expense')
        ->and($response->json('cycles'))->toHaveCount(1);

    $all = dashboardFor($this, query: ['financial_cycle_id' => 'all'])->assertOk()->assertJsonPath('cycle_id', 'all')->json('data');

    expect($all['charts']['categories'])->toHaveCount(12)->and($all['charts']['categories'][11])->toBe('Apr 2026')
        ->and($all['charts']['collection'])->toBeNull()
        ->and($all['balance']['amount'])->toBe(15000);
});

test('the attention lists count what is waiting for approval and who has not paid', function () {
    $this->actingAs($this->chair)->postJson(route('contributions.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => '2026-04-10', 'items' => [['group_fund_id' => $this->fund->id, 'amount' => 1000]],
    ])->assertCreated();

    $attention = dashboardFor($this)->assertOk()->json('data.attention');

    expect($attention['pending_contributions']['count'])->toBe(1)
        ->and($attention['pending_contributions']['items'][0]['member'])->toBe($this->plainMember->name)
        ->and(collect($attention['unpaid_members'])->pluck('name'))->toContain($this->plainMember->name);
});

test('members see only their own figures and never the group balance', function () {
    payFor($this, $this->plainMember, '2026-03', 20000);

    $plain = dashboardFor($this, $this->plain)->assertOk()->assertJsonPath('data.scope', 'member')->json('data');
    $friend = dashboardFor($this, $this->friend)->assertOk()->json('data');

    expect($plain['balance'])->toBeNull()
        ->and($plain['cards']['contributions'])->toBe(20000)
        ->and($plain['charts']['money_flow'])->toBeNull()
        ->and($plain['recent'])->toHaveCount(1)
        ->and($friend['cards']['contributions'])->toBe(0)->and($friend['recent'])->toBe([]);
});

test('an outsider cannot see the group dashboard', function () {
    dashboardFor($this, $this->outsider)->assertForbidden();
});
