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

test('contributions can be added for many members at once', function () {
    $payload = [
        'group_id' => $this->group->id,
        'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => now()->toDateString(),
        'rows' => [
            ['group_member_id' => $this->plainMember->id, 'items' => [['group_fund_id' => $this->savings->id, 'amount' => 20000], ['group_fund_id' => $this->social->id, 'amount' => 5000]]],
            ['group_member_id' => $this->treasurerMember->id, 'items' => [['group_fund_id' => $this->savings->id, 'amount' => 10000]]],
        ],
    ];

    $this->actingAs($this->treasurer)->postJson(route('contributions.store-many'), $payload)->assertCreated()->assertJsonPath('data.count', 2);

    expect($this->group->contributions()->where('status', 'pending')->count())->toBe(2)
        ->and((float) $this->group->contributions()->sum('total_amount'))->toBe(35000.0);

    $this->actingAs($this->plain)->postJson(route('contributions.store-many'), $payload)->assertForbidden();
});

test('a batch with a member twice is rejected and nothing is recorded', function () {
    $row = ['group_member_id' => $this->plainMember->id, 'items' => [['group_fund_id' => $this->savings->id, 'amount' => 1000]]];

    $this->actingAs($this->chair)->postJson(route('contributions.store-many'), [
        'group_id' => $this->group->id,
        'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => now()->toDateString(),
        'rows' => [$row, $row],
    ])->assertUnprocessable()->assertJsonValidationErrors('rows.1.group_member_id');

    expect($this->group->contributions()->count())->toBe(0);
});

test('many pending contributions can be approved at once', function () {
    $ids = collect([$this->plainMember, $this->treasurerMember])->map(fn ($member) => $this->actingAs($this->chair)
        ->postJson(route('contributions.store'), contributionPayload($this, ['group_member_id' => $member->id]))->json('data.id'));
    $approvedId = $this->actingAs($this->chair)->postJson(route('contributions.store'), contributionPayload($this))->json('data.id');
    $this->actingAs($this->chair)->patchJson(route('contributions.approve', $approvedId))->assertOk();

    $this->actingAs($this->plain)->postJson(route('contributions.approve-many'), ['group_id' => $this->group->id, 'ids' => $ids->all()])->assertForbidden();

    $this->actingAs($this->chair)->postJson(route('contributions.approve-many'), ['group_id' => $this->group->id, 'ids' => [...$ids, $approvedId]])
        ->assertOk()->assertJsonPath('data.approved', 2)->assertJsonPath('data.skipped', 1);

    expect($this->group->contributions()->where('status', 'pending')->count())->toBe(0)
        ->and($this->group->paymentTransactions()->count())->toBe(3);
});
