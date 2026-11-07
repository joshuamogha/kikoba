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
    $this->fund = createFundFor($this->group, ['name' => 'General Fund', 'code' => 'GEN']);
});

/**
 * @return array<string, mixed>
 */
function expensePayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_fund_id' => $test->fund->id,
        'group_expense_category_id' => $test->group->groupExpenseCategories()->value('id'),
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'expense_date' => now()->toDateString(),
        'description' => 'Hall hire for the AGM',
        'amount' => 45000,
        'payee_name' => 'Community Hall',
        ...$overrides,
    ];
}

test('an expense moves from draft to approved to paid and the payment is logged', function () {
    $id = $this->actingAs($this->treasurer)->postJson(route('group-expenses.store'), expensePayload($this))
        ->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('group-expenses.pay', $id))->assertUnprocessable();
    $this->actingAs($this->chair)->patchJson(route('group-expenses.approve', $id))->assertOk()->assertJsonPath('data.status', 'approved');
    $this->actingAs($this->chair)->patchJson(route('group-expenses.approve', $id))->assertUnprocessable();
    $this->actingAs($this->chair)->patchJson(route('group-expenses.pay', $id))->assertOk()->assertJsonPath('data.status', 'paid');

    $expense = $this->group->groupExpenses()->sole();
    $transaction = $this->group->paymentTransactions()->sole();

    expect($expense->expense_number)->toBe(sprintf('EXP-%d-%06d', now()->year, $expense->id))
        ->and($expense->paid_at)->not->toBeNull()
        ->and($transaction->transaction_type)->toBe('expense')
        ->and((float) $transaction->amount)->toBe(45000.0);
});

test('plain members and outsiders cannot manage expenses', function () {
    $this->actingAs($this->plain)->postJson(route('group-expenses.store'), expensePayload($this))->assertForbidden();

    $id = $this->actingAs($this->chair)->postJson(route('group-expenses.store'), expensePayload($this))->json('data.id');
    $this->actingAs($this->plain)->patchJson(route('group-expenses.approve', $id))->assertForbidden();

    $this->actingAs($this->plain)->get(route('group-expenses.index'))->assertOk()->assertSee('<group-expenses', false);
    $this->actingAs($this->plain)->getJson(route('group-expenses.index', ['group_id' => $this->group->id]))->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->outsider)->getJson(route('group-expenses.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('an expense needs a payee and this group\'s category', function () {
    $this->actingAs($this->chair)->postJson(route('group-expenses.store'), expensePayload($this, [
        'payee_name' => '',
        'group_expense_category_id' => $this->other->groupExpenseCategories()->value('id'),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['payee_name', 'group_expense_category_id']);
});
