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
    $this->fineType = $this->group->fineTypes()->create([
        'name' => 'Absent', 'code' => 'ABSENT', 'default_amount' => 2000, 'calculation_type' => 'fixed', 'status' => 'active',
    ]);
});

/**
 * @return array<string, mixed>
 */
function fineIssuePayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_member_id' => $test->plainMember->id,
        'fine_type_id' => $test->fineType->id,
        'reason' => 'Missed the monthly meeting',
        'fine_date' => now()->toDateString(),
        'amount' => 2000,
        ...$overrides,
    ];
}

test('a fine is issued pending with the full amount as balance', function () {
    $this->actingAs($this->treasurer)->postJson(route('member-fines.store'), fineIssuePayload($this))
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.balance', 2000);

    $fine = $this->group->memberFines()->sole();

    expect($fine->fine_number)->toBe(sprintf('FN-%d-%06d', now()->year, $fine->id))
        ->and($fine->financialCycle->status)->toBe('open')
        ->and($fine->issued_by)->toBe($this->treasurer->id);

    $this->actingAs($this->plain)->postJson(route('member-fines.store'), fineIssuePayload($this))->assertForbidden();
});

test('fine types, members and dates are validated within the group', function () {
    $foreignType = $this->other->fineTypes()->create(['name' => 'X', 'code' => 'X', 'default_amount' => 1, 'calculation_type' => 'fixed', 'status' => 'active']);

    $this->actingAs($this->chair)->postJson(route('member-fines.store'), fineIssuePayload($this, [
        'fine_type_id' => $foreignType->id,
        'amount' => 0,
        'due_date' => now()->subDay()->toDateString(),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['fine_type_id', 'amount', 'due_date']);
});

test('payments reduce the balance, flip the status and log a transaction', function () {
    $id = $this->actingAs($this->chair)->postJson(route('member-fines.store'), fineIssuePayload($this))->json('data.id');
    $payment = ['payment_method_id' => $this->group->paymentMethods()->value('id'), 'payment_date' => now()->toDateString()];

    $this->actingAs($this->chair)->postJson(route('member-fines.pay', $id), [...$payment, 'amount' => 500])
        ->assertOk()->assertJsonPath('data.status', 'partial')->assertJsonPath('data.balance', 1500)->assertJsonPath('data.amount_paid', 500);
    $this->actingAs($this->chair)->postJson(route('member-fines.pay', $id), [...$payment, 'amount' => 1600])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->actingAs($this->chair)->postJson(route('member-fines.pay', $id), [...$payment, 'amount' => 1500])
        ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.balance', 0);
    $this->actingAs($this->chair)->postJson(route('member-fines.pay', $id), [...$payment, 'amount' => 1])->assertUnprocessable();

    expect($this->group->paymentTransactions()->where('transaction_type', 'fine_payment')->count())->toBe(2)
        ->and($this->group->paymentTransactions()->first()->group_member_id)->toBe($this->plainMember->id);
});

test('an unpaid fine can be waived with a reason, but only by managers', function () {
    $id = $this->actingAs($this->chair)->postJson(route('member-fines.store'), fineIssuePayload($this))->json('data.id');

    $this->actingAs($this->plain)->patchJson(route('member-fines.waive', $id), ['waiver_reason' => 'Please'])->assertForbidden();
    $this->actingAs($this->chair)->patchJson(route('member-fines.waive', $id), [])->assertUnprocessable()->assertJsonValidationErrors('waiver_reason');
    $this->actingAs($this->chair)->patchJson(route('member-fines.waive', $id), ['waiver_reason' => 'Was in hospital'])
        ->assertOk()->assertJsonPath('data.status', 'waived')->assertJsonPath('data.balance', 0);
    $this->actingAs($this->chair)->patchJson(route('member-fines.waive', $id), ['waiver_reason' => 'Again'])->assertUnprocessable();
});

test('members see only their own fines and managers see all', function () {
    $this->actingAs($this->chair)->postJson(route('member-fines.store'), fineIssuePayload($this))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('member-fines.store'), fineIssuePayload($this, ['group_member_id' => $this->treasurerMember->id]))->assertCreated();

    $this->actingAs($this->plain)->get(route('member-fines.index'))->assertRedirect(route('payment-pages.show', 'fines'));
    $this->actingAs($this->plain)->getJson(route('member-fines.index', ['group_id' => $this->group->id]))
        ->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false)->assertJsonPath('options', null);
    $this->actingAs($this->treasurer)->getJson(route('member-fines.index', ['group_id' => $this->group->id]))
        ->assertJsonCount(2, 'data')->assertJsonCount(1, 'options.fine_types');
    $this->actingAs($this->outsider)->getJson(route('member-fines.index', ['group_id' => $this->group->id]))->assertForbidden();
});
