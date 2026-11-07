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
 * Submit and approve a loan application for the plain member.
 */
function approvedApplication($test, float $amount = 100000, int $months = 3): int
{
    $id = $test->actingAs($test->plain)->postJson(route('loan-applications.store'), [
        'group_id' => $test->group->id, 'group_member_id' => $test->plainMember->id, 'loan_product_id' => $test->product->id,
        'requested_amount' => $amount, 'duration_months' => $months, 'purpose' => 'Business',
    ])->json('data.id');

    $test->actingAs($test->chair)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => $amount])->assertOk();

    return $id;
}

test('disbursing an approved application creates the loan and its schedule', function () {
    $applicationId = approvedApplication($this);

    $this->actingAs($this->treasurer)->postJson(route('loans.store'), [
        'group_id' => $this->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString(),
    ])->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.interest_amount', 15000)->assertJsonPath('data.total_payable', 115000);

    $loan = $this->group->loans()->sole();

    expect($loan->loan_number)->toBe(sprintf('LN-%d-%06d', now()->year, $loan->id))
        ->and($loan->loanInstallments)->toHaveCount(3)
        ->and(round($loan->loanInstallments->sum('principal_due'), 2))->toBe(100000.0)
        ->and(round($loan->loanInstallments->sum('total_due'), 2))->toBe(115000.0)
        ->and($loan->first_payment_date->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($loan->maturity_date->toDateString())->toBe(now()->addDays(90)->toDateString());
});

test('an application is disbursed once, only when approved, and only by managers', function () {
    $applicationId = approvedApplication($this);
    $payload = ['group_id' => $this->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString()];

    $this->actingAs($this->plain)->postJson(route('loans.store'), $payload)->assertForbidden();
    $this->actingAs($this->chair)->postJson(route('loans.store'), $payload)->assertCreated();
    $this->actingAs($this->chair)->postJson(route('loans.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('loan_application_id');

    $pending = $this->actingAs($this->friend)->postJson(route('loan-applications.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->friendMember->id, 'loan_product_id' => $this->product->id,
        'requested_amount' => 50000, 'duration_months' => 3, 'purpose' => 'Rent',
    ])->json('data.id');

    $this->actingAs($this->chair)->postJson(route('loans.store'), [...$payload, 'loan_application_id' => $pending])->assertUnprocessable();
});

test('reducing balance loans total less interest than flat and still repay the principal', function () {
    $this->product->update(['loan_interest_method_id' => $this->group->loanInterestMethods()->where('code', 'REDUCING')->value('id')]);
    $applicationId = approvedApplication($this, 100000, 4);

    $this->actingAs($this->chair)->postJson(route('loans.store'), [
        'group_id' => $this->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString(),
    ])->assertCreated();

    $loan = $this->group->loans()->sole();

    expect((float) $loan->interest_amount)->toBeLessThan(20000.0)
        ->and(round($loan->loanInstallments->sum('principal_due'), 2))->toBe(100000.0);
});

test('the loan list and detail follow member visibility', function () {
    $applicationId = approvedApplication($this);
    $this->actingAs($this->chair)->postJson(route('loans.store'), ['group_id' => $this->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString()])->assertCreated();
    $loan = $this->group->loans()->sole();

    $this->actingAs($this->plain)->get(route('loans.index'))->assertOk()->assertSee('<loans', false);
    $this->actingAs($this->plain)->getJson(route('loans.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false);
    $this->actingAs($this->friend)->getJson(route('loans.index', ['group_id' => $this->group->id]))->assertJsonCount(0, 'data');
    $this->actingAs($this->treasurer)->getJson(route('loans.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data');

    $this->actingAs($this->plain)->getJson(route('loans.show', $loan))->assertOk()->assertJsonCount(3, 'data.installments');
    $this->actingAs($this->friend)->getJson(route('loans.show', $loan))->assertForbidden();
    $this->actingAs($this->outsider)->getJson(route('loans.show', $loan))->assertForbidden();
});
