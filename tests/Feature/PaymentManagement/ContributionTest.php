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

beforeEach(function () {
    $this->savings = createFundFor($this->group);
    $this->social = createFundFor($this->group, ['name' => 'Social Fund', 'code' => 'SOC']);
});

/**
 * @return array<string, mixed>
 */
function contributionPayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_member_id' => $test->plainMember->id,
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'contribution_date' => now()->toDateString(),
        'items' => [
            ['group_fund_id' => $test->savings->id, 'amount' => 20000],
            ['group_fund_id' => $test->social->id, 'amount' => 5000],
        ],
        ...$overrides,
    ];
}

test('a contribution totals its fund items and opens a cycle', function () {
    $this->actingAs($this->treasurer)->postJson(route('contributions.store'), contributionPayload($this))
        ->assertCreated()
        ->assertJsonPath('data.total_amount', 25000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonCount(2, 'data.funds');

    $contribution = $this->group->contributions()->firstOrFail();

    expect($contribution->contribution_number)->toBe(sprintf('CON-%d-%06d', now()->year, $contribution->id))
        ->and($contribution->financialCycle->status)->toBe('open')
        ->and($contribution->contributionItems()->count())->toBe(2);
});

test('approving a contribution logs one completed payment transaction', function () {
    $id = $this->actingAs($this->chair)->postJson(route('contributions.store'), contributionPayload($this))->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('contributions.approve', $id))->assertOk()->assertJsonPath('data.status', 'approved');
    $this->actingAs($this->chair)->patchJson(route('contributions.approve', $id))->assertUnprocessable();

    $transaction = $this->group->paymentTransactions()->sole();

    expect($transaction->transaction_type)->toBe('contribution')
        ->and((float) $transaction->amount)->toBe(25000.0)
        ->and($transaction->group_member_id)->toBe($this->plainMember->id)
        ->and($transaction->status)->toBe('completed');
});

test('plain members cannot record or approve contributions but see their own', function () {
    $this->actingAs($this->plain)->postJson(route('contributions.store'), contributionPayload($this))->assertForbidden();

    $mine = $this->actingAs($this->chair)->postJson(route('contributions.store'), contributionPayload($this))->json('data.id');
    $this->actingAs($this->chair)->postJson(route('contributions.store'), contributionPayload($this, ['group_member_id' => $this->treasurerMember->id]))->assertCreated();

    $this->actingAs($this->plain)->patchJson(route('contributions.approve', $mine))->assertForbidden();

    $list = $this->actingAs($this->plain)->getJson(route('contributions.index', ['group_id' => $this->group->id]))->assertOk();

    expect($list->json('data'))->toHaveCount(1)->and($list->json('options'))->toBeNull();
    $this->actingAs($this->plain)->get(route('contributions.index'))->assertOk()->assertSee('<contributions', false);
});

test('members, methods and funds of another group are rejected and amounts must be positive', function () {
    $foreignMember = addMemberTo($this->other, User::factory()->create());

    $this->actingAs($this->chair)->postJson(route('contributions.store'), contributionPayload($this, [
        'group_member_id' => $foreignMember->id,
        'payment_method_id' => $this->other->paymentMethods()->value('id'),
        'items' => [['group_fund_id' => createFundFor($this->other)->id, 'amount' => 0]],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['group_member_id', 'payment_method_id', 'items.0.group_fund_id', 'items.0.amount']);
});
