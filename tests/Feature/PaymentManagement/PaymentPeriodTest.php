<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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

test('a contribution records the month it is for and defaults to the month it was paid', function () {
    $january = payFor($this, $this->plainMember, '2026-01', 20000, '2026-02-05');
    $this->actingAs($this->chair)->postJson(route('contributions.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => '2026-03-20', 'items' => [['group_fund_id' => $this->fund->id, 'amount' => 1000]],
    ])->assertCreated()->assertJsonPath('data.period', '2026-03');

    $item = $this->group->contributions()->findOrFail($january)->contributionItems()->sole();

    expect($item->period_start->toDateString())->toBe('2026-01-01')->and($item->period_end->toDateString())->toBe('2026-01-31');
    $this->actingAs($this->chair)->getJson(route('contributions.index', ['group_id' => $this->group->id]))->assertJsonPath('data.1.period', '2026-01');
});

test('a payment belongs to the cycle of the month it is paid for, not the day it is paid', function () {
    $this->travelTo('2027-01-10 10:00:00');

    $id = payFor($this, $this->plainMember, '2026-12', 20000, '2027-01-08');

    expect($this->group->contributions()->findOrFail($id)->financial_cycle_id)->toBe($this->cycle->id)
        ->and($this->group->financialCycles()->count())->toBe(1);
});

test('invalid periods are rejected', function () {
    foreach (['2026-13', 'January', '2026-1', '1999-12', '2099-01'] as $bad) {
        $this->actingAs($this->chair)->postJson(route('contributions.store'), [
            'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
            'contribution_date' => '2026-04-10', 'period' => $bad, 'items' => [['group_fund_id' => $this->fund->id, 'amount' => 1000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), [
            'group_member_id' => $this->plainMember->id, 'number_of_shares' => 1, 'transaction_date' => '2026-04-10', 'period' => $bad,
        ])->assertUnprocessable()->assertJsonValidationErrors('period');
    }
});

test('share purchases record the month they are for and default to the month paid', function () {
    buyShares($this, $this->plainMember, '2026-01', 2, '2026-02-05');
    $this->actingAs($this->chair)->postJson(route('groups.shares.store', $this->group), [
        'group_member_id' => $this->plainMember->id, 'number_of_shares' => 1, 'transaction_date' => '2026-03-02',
    ])->assertCreated()->assertJsonPath('data.period', '2026-03');

    $first = $this->group->memberShareTransactions()->orderBy('id')->first();

    expect($first->period_start->toDateString())->toBe('2026-01-01')->and($first->financial_cycle_id)->toBe($this->cycle->id);
    $this->actingAs($this->chair)->getJson(route('groups.shares.index', $this->group))->assertJsonPath('data.1.period', '2026-01');
});

test('the share import puts each purchase in the month of its transaction date', function () {
    $phone = $this->plainMember->phone_number;
    $price = (float) $this->group->groupSetting->share_price;
    $csv = "phone_number,amount,transaction_date\n{$phone},".($price * 2).",2026-02-05\n{$phone},{$price},2026-03-02\n";

    $this->actingAs($this->chair)->postJson(route('groups.shares.import', $this->group), ['file' => UploadedFile::fake()->createWithContent('shares.csv', $csv)])
        ->assertOk()->assertJsonPath('data.imported', 2);

    expect($this->group->memberShareTransactions()->orderBy('id')->pluck('period_start')->map->toDateString()->all())->toBe(['2026-02-01', '2026-03-01']);
});
