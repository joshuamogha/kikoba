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
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();
    $this->other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $this->product = createLoanProductFor($this->group);
});

/**
 * A disbursed 100,000 loan over 3 months at 5% a month: three installments of about 38,333, total payable 115,000.
 */
function activeLoan($test): int
{
    $applicationId = approvedApplication($test);

    return $test->actingAs($test->chair)->postJson(route('loans.store'), [
        'group_id' => $test->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString(),
    ])->json('data.id');
}

function repaymentPayload($test, int $loanId, float $amount): array
{
    return [
        'group_id' => $test->group->id, 'loan_id' => $loanId, 'amount' => $amount, 'payment_date' => now()->toDateString(),
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
    ];
}

test('a repayment is recorded pending and cannot exceed the outstanding balance', function () {
    $loanId = activeLoan($this);

    $this->actingAs($this->treasurer)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 20000))
        ->assertCreated()->assertJsonPath('data.status', 'pending');

    $this->actingAs($this->treasurer)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 115001))
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->actingAs($this->plain)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 1000))->assertForbidden();
});

test('approving pays installments oldest first and logs a payment transaction', function () {
    $loanId = activeLoan($this);
    $id = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 40000))->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $id))
        ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.principal_amount', 33333.33)->assertJsonPath('data.interest_amount', 6666.67);
    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $id))->assertUnprocessable();

    $loan = $this->group->loans()->sole();
    $installments = $loan->loanInstallments()->orderBy('installment_number')->get();

    expect((float) $loan->amount_paid)->toBe(40000.0)
        ->and((float) $loan->outstanding_balance)->toBe(75000.0)
        ->and($installments[0]->status)->toBe('paid')
        ->and($installments[1]->status)->toBe('partial')
        ->and($installments[2]->status)->toBe('pending')
        ->and($this->group->paymentTransactions()->sole()->transaction_type)->toBe('loan_repayment');
});

test('paying everything completes the loan and an overpayment at approval is refused', function () {
    $loanId = activeLoan($this);
    $first = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 100000))->json('data.id');
    $second = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 15000))->json('data.id');
    $third = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 15000))->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $first))->assertOk();
    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $second))->assertOk();
    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $third))->assertUnprocessable();

    $loan = $this->group->loans()->sole();

    expect($loan->status)->toBe('completed')
        ->and((float) $loan->outstanding_balance)->toBe(0.0)
        ->and($loan->loanInstallments()->where('status', '!=', 'paid')->count())->toBe(0);

    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 1000))->assertUnprocessable();
});

test('only managers approve repayments and members see their own', function () {
    $loanId = activeLoan($this);
    $id = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 10000))->json('data.id');

    $this->actingAs($this->plain)->patchJson(route('loan-repayments.approve', $id))->assertForbidden();

    $this->actingAs($this->plain)->get(route('loan-repayments.index'))->assertOk()->assertSee('<loan-repayments', false);
    $this->actingAs($this->plain)->getJson(route('loan-repayments.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data')->assertJsonPath('options', null);
    $this->actingAs($this->friend)->getJson(route('loan-repayments.index', ['group_id' => $this->group->id]))->assertJsonCount(0, 'data');
    $this->actingAs($this->treasurer)->getJson(route('loan-repayments.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data')->assertJsonCount(1, 'options.loans');
});

test('the overdue command flags late installments and applies the group penalty once', function () {
    $this->group->groupSetting()->update(['late_payment_penalty_value' => 2000]);
    $loanId = activeLoan($this);
    $loan = $this->group->loans()->findOrFail($loanId);
    $loan->loanInstallments()->where('installment_number', 1)->update(['due_date' => now()->subDays(3)->toDateString()]);

    $this->artisan('loans:mark-overdue')->assertSuccessful();
    $this->artisan('loans:mark-overdue')->assertSuccessful();

    $first = $loan->loanInstallments()->where('installment_number', 1)->first();
    $loan->refresh();

    expect($first->status)->toBe('overdue')
        ->and((float) $first->penalty_due)->toBe(2000.0)
        ->and($loan->status)->toBe('overdue')
        ->and((float) $loan->penalty_amount)->toBe(2000.0)
        ->and((float) $loan->outstanding_balance)->toBe(117000.0);
});

test('the repayment list carries the total loan, total repaid and balance', function () {
    $loanId = activeLoan($this);
    $id = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), repaymentPayload($this, $loanId, 40000))->json('data.id');
    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $id))->assertOk();

    $this->actingAs($this->chair)->getJson(route('loan-repayments.index', ['group_id' => $this->group->id]))
        ->assertJsonPath('totals.loan', 115000)->assertJsonPath('totals.repaid', 40000)->assertJsonPath('totals.balance', 75000);

    $this->actingAs($this->friend)->getJson(route('loan-repayments.index', ['group_id' => $this->group->id]))
        ->assertJsonPath('totals.loan', 0)->assertJsonPath('totals.balance', 0);
});
