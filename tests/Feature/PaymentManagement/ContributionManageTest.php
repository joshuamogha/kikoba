<?php

use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\PaymentTransaction;
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
    $this->outsider = User::factory()->create();

    foreach ([2025, 2026] as $year) {
        $this->group->financialCycles()->create([
            'contribution_frequency_id' => $this->group->contributionFrequencies()->value('id'),
            'name' => "Cycle {$year}", 'cycle_number' => $year, 'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => 'open',
        ]);
    }

    $this->fund = createFundFor($this->group);
    $this->social = createFundFor($this->group, ['name' => 'Social', 'code' => 'SOC']);
    $this->group->groupSetting()->update(['share_price' => 5000, 'maximum_shares' => null]);
});

function editPayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_member_id' => $test->plainMember->id,
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'contribution_date' => '2026-05-02',
        'period' => '2026-04',
        'external_reference' => 'RCT-9',
        'notes' => 'Corrected',
        'items' => [['group_fund_id' => $test->fund->id, 'amount' => 15000], ['group_fund_id' => $test->social->id, 'amount' => 5000]],
        ...$overrides,
    ];
}

test('a contribution shows its details to leaders and, for their own, to members', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);
    payFor($this, $this->friendMember, '2026-02', 5000);
    $friendId = Contribution::query()->where('group_member_id', $this->friendMember->id)->value('id');

    $this->actingAs($this->chair)->getJson(route('contributions.show', $id))->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.member.name', $this->plainMember->name)
        ->assertJsonPath('data.cycle', 'Cycle 2026')
        ->assertJsonPath('data.period', '2026-02')
        ->assertJsonPath('data.funds.0.name', 'Savings')
        ->assertJsonPath('data.approved_by', $this->chair->name)
        ->assertJsonPath('data.editable', true)
        ->assertJsonPath('data.share_linked', false);

    expect($this->actingAs($this->chair)->getJson(route('contributions.show', $id))->json('data.payment_transaction'))->toStartWith('TXN-');

    $this->actingAs($this->plain)->getJson(route('contributions.show', $id))->assertOk()->assertJsonPath('data.editable', false);
    $this->actingAs($this->plain)->getJson(route('contributions.show', $friendId))->assertForbidden();
    $this->actingAs($this->outsider)->getJson(route('contributions.show', $id))->assertForbidden();
});

test('an approved contribution can be edited and its payment transaction follows', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);
    $transaction = PaymentTransaction::query()->where('reference_id', $id)->sole();
    $number = Contribution::query()->findOrFail($id)->contribution_number;

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['group_member_id' => $this->friendMember->id]))
        ->assertOk()->assertJsonPath('data.total_amount', 20000)->assertJsonPath('data.period', '2026-04')->assertJsonCount(2, 'data.funds');

    $contribution = Contribution::query()->findOrFail($id);

    expect($contribution->contribution_number)->toBe($number)
        ->and($contribution->status)->toBe('approved')
        ->and($contribution->group_member_id)->toBe($this->friendMember->id)
        ->and($contribution->contribution_date->toDateString())->toBe('2026-05-02')
        ->and($contribution->external_reference)->toBe('RCT-9')
        ->and($contribution->notes)->toBe('Corrected')
        ->and($contribution->contributionItems()->count())->toBe(2)
        ->and((float) $contribution->contributionItems()->where('group_fund_id', $this->social->id)->value('amount'))->toBe(5000.0)
        ->and($contribution->contributionItems()->first()->period_start->toDateString())->toBe('2026-04-01');

    $transaction->refresh();

    expect((float) $transaction->amount)->toBe(20000.0)->and($transaction->group_member_id)->toBe($this->friendMember->id)->and($transaction->provider_transaction_id)->toBe('RCT-9');
});

test('changing the month moves the contribution to the cycle of that month and the total follows the funds', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, [
        'contribution_date' => '2026-01-05', 'period' => '2025-12', 'items' => [['group_fund_id' => $this->fund->id, 'amount' => 12000]],
    ]))->assertOk();

    $contribution = Contribution::query()->findOrFail($id);

    expect((float) $contribution->total_amount)->toBe(12000.0)
        ->and($contribution->financialCycle->name)->toBe('Cycle 2025')
        ->and((float) PaymentTransaction::query()->where('reference_id', $id)->value('amount'))->toBe(12000.0);
});

test('a pending contribution can be edited and deleted without a payment transaction', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000, approve: false);

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this))->assertOk()->assertJsonPath('data.status', 'pending');
    expect(PaymentTransaction::query()->where('reference_id', $id)->count())->toBe(0);

    $this->actingAs($this->chair)->deleteJson(route('contributions.destroy', $id))->assertOk();
    $this->assertDatabaseMissing('contributions', ['id' => $id]);
    $this->assertDatabaseMissing('contribution_items', ['contribution_id' => $id]);
});

test('deleting an approved contribution removes its items and payment transaction and updates the totals', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);
    $keep = payFor($this, $this->friendMember, '2026-02', 5000);

    $this->actingAs($this->chair)->deleteJson(route('contributions.destroy', $id))->assertOk();

    $this->assertDatabaseMissing('contributions', ['id' => $id]);
    expect(PaymentTransaction::query()->where('reference_id', $id)->where('reference_type', Contribution::class)->count())->toBe(0)
        ->and(PaymentTransaction::query()->where('reference_id', $keep)->count())->toBe(1);

    $this->actingAs($this->chair)->getJson(route('contributions.index', ['group_id' => $this->group->id]))->assertJsonPath('totals.count', 1)->assertJsonPath('totals.amount', 5000);
});

test('only leaders and the treasurer can edit or delete, and only within their own group', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);

    $this->actingAs($this->plain)->putJson(route('contributions.update', $id), editPayload($this))->assertForbidden();
    $this->actingAs($this->plain)->deleteJson(route('contributions.destroy', $id))->assertForbidden();
    $this->actingAs($this->outsider)->deleteJson(route('contributions.destroy', $id))->assertForbidden();
    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['group_id' => $other->id]))->assertNotFound();

    $this->assertDatabaseHas('contributions', ['id' => $id]);
});

test('an edit needs a fund amount, a real fund of the group and a date that is not in the future', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);
    $foreignFund = createFundFor(createGroupFor(User::factory()->create(), ['name' => 'Other']), ['name' => 'Foreign', 'code' => 'FOR']);

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['items' => []]))->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['items' => [['group_fund_id' => $foreignFund->id, 'amount' => 100]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.group_fund_id');
    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['contribution_date' => now()->addDay()->toDateString()]))
        ->assertUnprocessable()->assertJsonValidationErrors('contribution_date');
});

test('a fund that was deactivated after the payment can stay on the contribution, but cannot be added', function () {
    $id = payFor($this, $this->plainMember, '2026-02', 20000);
    $this->fund->update(['status' => 'inactive']);

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['items' => [['group_fund_id' => $this->fund->id, 'amount' => 18000]]]))->assertOk();
    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['items' => [['group_fund_id' => $this->fund->id, 'amount' => 18000], ['group_fund_id' => $this->social->id, 'amount' => 1]]]))->assertOk();

    $this->social->update(['status' => 'inactive']);
    $newFund = createFundFor($this->group, ['name' => 'Other', 'code' => 'OTH', 'status' => 'inactive']);

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this, ['items' => [['group_fund_id' => $newFund->id, 'amount' => 5]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.group_fund_id');
});

test('a contribution that records a share purchase is viewed here but changed on the Shares page', function () {
    createFundFor($this->group, [
        'fund_type_id' => $this->group->fundTypes()->where('code', 'SHR')->value('id'), 'name' => 'Hisa (Shares)', 'code' => 'SHR',
    ]);
    buyShares($this, $this->plainMember, '2026-02', 2, '2026-02-10');
    $id = Contribution::query()->latest('id')->value('id');

    $this->actingAs($this->chair)->getJson(route('contributions.show', $id))->assertOk()
        ->assertJsonPath('data.share_linked', true)->assertJsonPath('data.editable', false)->assertJsonPath('data.share_purchases.0.shares', 2);

    $this->actingAs($this->chair)->putJson(route('contributions.update', $id), editPayload($this))->assertUnprocessable()->assertJsonPath('error', fn ($message) => str_contains($message, 'Shares page'));
    $this->actingAs($this->chair)->deleteJson(route('contributions.destroy', $id))->assertUnprocessable();

    $this->assertDatabaseHas('contributions', ['id' => $id]);
});
