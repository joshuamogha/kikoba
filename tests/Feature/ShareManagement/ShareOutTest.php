<?php

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    $this->plain = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->chairMember = $this->group->groupMembers()->firstOrFail();
    $this->treasurerMember = addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $this->plainMember = addMemberTo($this->group, $this->plain);

    $this->cycle = FinancialCycle::factory()->create(['group_id' => $this->group->id]);
});

/**
 * Savings 100k / 50k / 50k, shares 3 / 1 / 0 at 10k, 4,000 of profit and a 70k fine on the plain member.
 */
function recordCycleActivity(): void
{
    $test = test();
    $group = $test->group;
    $now = now();
    $methodId = $group->paymentMethods()->value('id');
    $frequencyId = $group->contributionFrequencies()->value('id');
    $row = fn (array $values) => $values + ['created_at' => $now, 'updated_at' => $now];

    foreach (['SAV' => 'Savings', 'GEN' => 'General Fund'] as $code => $name) {
        DB::table('group_funds')->insert($row([
            'group_id' => $group->id,
            'fund_type_id' => DB::table('fund_types')->where('group_id', $group->id)->where('code', $code)->value('id'),
            'name' => $name,
            'code' => $code,
            'contribution_frequency_id' => $frequencyId,
            'created_by' => $test->chair->id,
        ]));
    }

    $fund = fn (string $code) => DB::table('group_funds')->where('group_id', $group->id)->where('code', $code)->value('id');

    $contribute = function ($member, int $amount, string $status, string $number) use ($row, $group, $test, $methodId, $fund): void {
        $id = DB::table('contributions')->insertGetId($row([
            'group_id' => $group->id,
            'financial_cycle_id' => $test->cycle->id,
            'group_member_id' => $member->id,
            'contribution_number' => $number,
            'contribution_date' => '2025-03-01',
            'total_amount' => $amount,
            'payment_method_id' => $methodId,
            'status' => $status,
            'received_by' => $test->chair->id,
            'approved_by' => $test->chair->id,
        ]));
        DB::table('contribution_items')->insert($row(['contribution_id' => $id, 'group_fund_id' => $fund('SAV'), 'amount' => $amount]));
    };

    $contribute($test->chairMember, 100000, 'approved', 'C-1');
    $contribute($test->treasurerMember, 50000, 'approved', 'C-2');
    $contribute($test->plainMember, 50000, 'approved', 'C-3');
    $contribute($test->plainMember, 999999, 'pending', 'C-4');

    foreach ([[$test->chairMember, 3, 'approved'], [$test->treasurerMember, 1, 'approved'], [$test->plainMember, 5, 'pending']] as $index => [$member, $shares, $status]) {
        DB::table('member_share_transactions')->insert($row([
            'group_id' => $group->id,
            'financial_cycle_id' => $test->cycle->id,
            'group_member_id' => $member->id,
            'transaction_number' => 'T-'.$index,
            'transaction_type' => 'purchase',
            'number_of_shares' => $shares,
            'price_per_share' => 10000,
            'total_amount' => $shares * 10000,
            'transaction_date' => '2025-03-01',
            'status' => $status,
            'approved_by' => $test->chair->id,
        ]));
    }

    DB::table('group_incomes')->insert($row([
        'group_id' => $group->id,
        'financial_cycle_id' => $test->cycle->id,
        'group_fund_id' => $fund('GEN'),
        'income_number' => 'I-1',
        'income_category_id' => DB::table('income_categories')->where('group_id', $group->id)->value('id'),
        'income_date' => '2025-06-01',
        'description' => 'Hall hire',
        'amount' => 8000,
        'payment_method_id' => $methodId,
        'received_by' => $test->chair->id,
        'approved_by' => $test->chair->id,
    ]));

    DB::table('group_expenses')->insert($row([
        'group_id' => $group->id,
        'financial_cycle_id' => $test->cycle->id,
        'group_fund_id' => $fund('GEN'),
        'expense_number' => 'E-1',
        'group_expense_category_id' => DB::table('group_expense_categories')->where('group_id', $group->id)->value('id'),
        'expense_date' => '2025-06-02',
        'description' => 'Stationery',
        'amount' => 4000,
        'payee_name' => 'Duka',
        'payment_method_id' => $methodId,
        'requested_by' => $test->chair->id,
        'approved_by' => $test->chair->id,
    ]));

    $fineTypeId = DB::table('fine_types')->insertGetId($row(['group_id' => $group->id, 'name' => 'Late', 'code' => 'LATE']));

    DB::table('member_fines')->insert($row([
        'group_id' => $group->id,
        'financial_cycle_id' => $test->cycle->id,
        'group_member_id' => $test->plainMember->id,
        'fine_type_id' => $fineTypeId,
        'fine_number' => 'F-1',
        'reason' => 'Late',
        'fine_date' => '2025-04-01',
        'amount' => 70000,
        'balance' => 70000,
        'issued_by' => $test->chair->id,
    ]));
}

function calculateShareOut(?User $by = null)
{
    return test()->actingAs($by ?? test()->treasurer)->postJson(route('groups.share-outs.store', test()->group), [
        'financial_cycle_id' => test()->cycle->id,
    ]);
}

function approvedShareOut(): ShareOut
{
    recordCycleActivity();
    calculateShareOut()->assertCreated();
    $shareOut = ShareOut::query()->firstOrFail();
    test()->actingAs(test()->chair)->patchJson(route('groups.share-outs.approve', [test()->group, $shareOut]))->assertOk();

    return $shareOut->refresh();
}

test('the share out is calculated from approved cycle activity', function () {
    recordCycleActivity();

    calculateShareOut()->assertCreated()->assertJsonStructure(['message', 'url']);

    $shareOut = ShareOut::query()->firstOrFail();

    expect($shareOut->status)->toBe('draft')
        ->and($shareOut->share_out_number)->toBe("G{$this->group->id}-SO-2025-01")
        ->and((float) $shareOut->total_savings)->toBe(200000.0)
        ->and((float) $shareOut->total_share_value)->toBe(40000.0)
        ->and((float) $shareOut->distributable_profit)->toBe(4000.0)
        ->and((float) $shareOut->total_distributable)->toBe(194000.0);

    $rows = $shareOut->memberShareOuts()->pluck('net_payable', 'group_member_id');

    expect($rows)->toHaveCount(3)
        ->and((float) $rows[$this->chairMember->id])->toBe(133000.0)
        ->and((float) $rows[$this->treasurerMember->id])->toBe(61000.0)
        ->and((float) $rows[$this->plainMember->id])->toBe(0.0);
});

test('a share out needs a closed cycle without an existing share out', function () {
    $this->cycle->update(['status' => 'active']);
    calculateShareOut()->assertUnprocessable();

    $this->cycle->update(['status' => 'closed']);
    calculateShareOut()->assertCreated();
    calculateShareOut()->assertUnprocessable()->assertJsonValidationErrors('financial_cycle_id');
});

test('a cycle of another group cannot be shared out', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $otherCycle = FinancialCycle::factory()->create(['group_id' => $other->id]);

    $this->actingAs($this->chair)
        ->postJson(route('groups.share-outs.store', $this->group), ['financial_cycle_id' => $otherCycle->id])
        ->assertJsonValidationErrors('financial_cycle_id');
});

test('a draft can be recalculated and deleted but an approved share out is locked', function () {
    recordCycleActivity();
    calculateShareOut();
    $shareOut = ShareOut::query()->firstOrFail();

    $this->actingAs($this->chair)->putJson(route('groups.share-outs.update', [$this->group, $shareOut]))->assertOk();
    expect($shareOut->memberShareOuts()->count())->toBe(3)->and(MemberShareOut::query()->count())->toBe(3);

    $this->patchJson(route('groups.share-outs.approve', [$this->group, $shareOut]))->assertOk();
    $shareOut->refresh();

    expect($shareOut->status)->toBe('approved')->and($shareOut->approved_by)->toBe($this->chair->id);
    // The member owed nothing needs no payment.
    expect(MemberShareOut::query()->where('group_member_id', $this->plainMember->id)->value('status'))->toBe('paid');

    $this->putJson(route('groups.share-outs.update', [$this->group, $shareOut]))->assertUnprocessable();
    $this->deleteJson(route('groups.share-outs.destroy', [$this->group, $shareOut]))->assertUnprocessable();
    $this->patchJson(route('groups.share-outs.approve', [$this->group, $shareOut]))->assertUnprocessable();
});

test('a draft share out can be deleted', function () {
    recordCycleActivity();
    calculateShareOut();

    $this->actingAs($this->chair)
        ->deleteJson(route('groups.share-outs.destroy', [$this->group, ShareOut::query()->firstOrFail()]))
        ->assertOk();

    expect(ShareOut::query()->count())->toBe(0)->and(MemberShareOut::query()->count())->toBe(0);
});

test('only leaders and the treasurer manage share outs', function (string $who, int $expected) {
    recordCycleActivity();

    calculateShareOut($this->{$who})->assertStatus($expected);
})->with([
    'chairperson' => ['chair', 201],
    'treasurer' => ['treasurer', 201],
    'member' => ['plain', 403],
    'outsider' => ['outsider', 403],
]);

test('a member sees only their own share while a manager sees everyone', function () {
    approvedShareOut();
    $shareOut = ShareOut::query()->firstOrFail();
    $show = route('groups.share-outs.show', [$this->group, $shareOut]);

    $this->actingAs($this->treasurer)->getJson($show)->assertOk()->assertJsonCount(3, 'data.members');

    $this->actingAs($this->plain)->getJson($show)
        ->assertOk()
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.member.is_self', true);

    $this->actingAs($this->outsider)->getJson($show)->assertForbidden();
    $this->actingAs($this->plain)->get($show)->assertOk()->assertSee('share-out-detail', false);
    $this->actingAs($this->plain)->get(route('groups.share-outs.index', $this->group))->assertOk()->assertSee('share-outs', false);
});

test('a share out of another group is not found', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreign = ShareOut::factory()->create(['group_id' => $other->id]);

    $this->actingAs($this->chair)
        ->getJson(route('groups.share-outs.show', [$this->group, $foreign]))
        ->assertNotFound();
});

test('payments need an approved share out and cannot exceed what is owed', function () {
    recordCycleActivity();
    calculateShareOut();
    $shareOut = ShareOut::query()->firstOrFail();
    $row = $shareOut->memberShareOuts()->where('group_member_id', $this->treasurerMember->id)->firstOrFail();
    $pay = fn (float $amount, array $extra = []) => $this->actingAs($this->treasurer)->postJson(
        route('groups.share-outs.payments.store', [$this->group, $shareOut]),
        ['member_share_out_id' => $row->id, 'amount' => $amount, 'payment_method_id' => $this->group->paymentMethods()->value('id'), 'payment_date' => now()->toDateString(), ...$extra],
    );

    $pay(1000)->assertUnprocessable();

    $this->actingAs($this->chair)->patchJson(route('groups.share-outs.approve', [$this->group, $shareOut]))->assertOk();

    $pay(61001)->assertUnprocessable()->assertJsonValidationErrors('amount');
    $pay(1000, ['payment_method_id' => DB::table('payment_methods')->where('group_id', '!=', $this->group->id)->value('id') ?? 999999])->assertUnprocessable();
    expect($row->refresh()->amount_paid)->toBe('0.00');
});

test('partial and full payments settle the member and complete the share out', function () {
    $shareOut = approvedShareOut();
    $methodId = $this->group->paymentMethods()->value('id');
    $rowFor = fn ($member) => $shareOut->memberShareOuts()->where('group_member_id', $member->id)->firstOrFail();
    $pay = fn ($member, float $amount) => $this->actingAs($this->treasurer)->postJson(
        route('groups.share-outs.payments.store', [$this->group, $shareOut]),
        ['member_share_out_id' => $rowFor($member)->id, 'amount' => $amount, 'payment_method_id' => $methodId, 'payment_date' => now()->toDateString(), 'external_reference' => 'MP123'],
    );

    $pay($this->chairMember, 100000)->assertCreated();
    expect($rowFor($this->chairMember))->status->toBe('partial')->amount_paid->toBe('100000.00');

    $pay($this->chairMember, 33000)->assertCreated();
    expect($rowFor($this->chairMember))->status->toBe('paid')->paid_at->not->toBeNull();
    expect($shareOut->refresh()->status)->toBe('approved');

    $pay($this->chairMember, 1)->assertUnprocessable();
    $pay($this->treasurerMember, 61000)->assertCreated();

    expect($shareOut->refresh()->status)->toBe('completed')->and($shareOut->completed_at)->not->toBeNull();
    expect($shareOut->memberShareOuts()->first()->shareOutPayments()->first()->payment_number)->toStartWith($shareOut->share_out_number.'-P');
    $pay($this->treasurerMember, 1)->assertUnprocessable();
});

test('a member cannot record payments', function () {
    $shareOut = approvedShareOut();

    $this->actingAs($this->plain)->postJson(route('groups.share-outs.payments.store', [$this->group, $shareOut]), [])->assertForbidden();
});
