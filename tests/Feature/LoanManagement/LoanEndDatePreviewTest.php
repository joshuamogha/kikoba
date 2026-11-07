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
    $this->outsider = User::factory()->create();

    $this->product = createLoanProductFor($this->group, ['repayment_mode' => 'lump_sum']);
    $this->product->loanProductRanges()->createMany([
        ['minimum_amount' => 0, 'maximum_amount' => 100000, 'duration_months' => 1],
        ['minimum_amount' => 100001, 'maximum_amount' => 500000, 'duration_months' => 3],
    ]);
});

/**
 * @param  array<string, mixed>  $query
 */
function previewLoan($test, array $query, ?User $as = null)
{
    return $test->actingAs($as ?? $test->plain)->getJson(route('loan-applications.preview', ['group_id' => $test->group->id, 'loan_product_id' => $test->product->id, ...$query]));
}

test('the preview gives the term from the amount and the end date', function () {
    previewLoan($this, ['amount' => 80000])->assertOk()
        ->assertJsonPath('data.term_months', 1)->assertJsonPath('data.end_date', '2026-05-15')->assertJsonPath('data.repayment_mode', 'lump_sum');
    previewLoan($this, ['amount' => 250000])->assertJsonPath('data.term_months', 3)->assertJsonPath('data.end_date', '2026-07-15');
});

test('the end date counts from the disbursement date, keeps month ends and adds the grace period', function () {
    previewLoan($this, ['amount' => 80000, 'disbursement_date' => '2026-01-31'])->assertJsonPath('data.end_date', '2026-02-28');

    $this->product->update(['grace_period_days' => 10]);

    previewLoan($this, ['amount' => 80000])->assertJsonPath('data.end_date', '2026-05-25');
});

test('an amount no range covers or a missing duration has no end date', function () {
    previewLoan($this, ['amount' => 900000])->assertOk()->assertJsonPath('data.end_date', null)->assertJsonPath('data.message', 'No loan range covers this amount.');

    $free = createLoanProductFor($this->group);

    previewLoan($this, ['loan_product_id' => $free->id, 'amount' => 50000])->assertJsonPath('data.end_date', null)->assertJsonPath('data.message', 'Enter the duration.');
    previewLoan($this, ['loan_product_id' => $free->id, 'amount' => 50000, 'duration_months' => 3])
        ->assertJsonPath('data.term_months', 3)->assertJsonPath('data.end_date', '2026-07-14')->assertJsonPath('data.repayment_mode', 'installments');
});

test('the preview matches the maturity date the loan really gets', function () {
    foreach ([['lump_sum', 80000], ['installments', 250000]] as [$mode, $amount]) {
        $this->product->update(['repayment_mode' => $mode]);
        $this->group->groupSetting()->update(['allow_multiple_loans' => true]);

        $application = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), [
            'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'loan_product_id' => $this->product->id,
            'requested_amount' => $amount, 'purpose' => 'Business',
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $application), ['approved_amount' => $amount])->assertOk();

        $expected = previewLoan($this, ['amount' => $amount])->json('data.end_date');
        $loan = $this->actingAs($this->chair)->postJson(route('loans.store'), ['group_id' => $this->group->id, 'loan_application_id' => $application, 'disbursement_date' => now()->toDateString()])->json('data.id');

        expect($this->group->loans()->findOrFail($loan)->maturity_date->toDateString())->toBe($expected);
    }
});

test('the preview needs a product of the group and a group the user can see', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);

    previewLoan($this, ['amount' => 1000, 'loan_product_id' => createLoanProductFor($other)->id])->assertUnprocessable()->assertJsonValidationErrors('loan_product_id');
    previewLoan($this, ['amount' => 1000], $this->outsider)->assertForbidden();
    previewLoan($this, ['amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');
});

test('applications list when they end: expected before disbursement, the real date after, none when rejected', function () {
    $submitted = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'loan_product_id' => $this->product->id, 'requested_amount' => 80000, 'purpose' => 'Business',
    ])->assertCreated()->assertJsonPath('data.end_date', '2026-05-15')->assertJsonPath('data.end_date_is_expected', true)->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $submitted), ['approved_amount' => 80000])->assertOk();
    $this->travelTo('2026-04-20 10:00:00');
    $this->actingAs($this->chair)->postJson(route('loans.store'), ['group_id' => $this->group->id, 'loan_application_id' => $submitted, 'disbursement_date' => '2026-04-18'])->assertCreated();

    $row = collect($this->actingAs($this->chair)->getJson(route('loan-applications.index', ['group_id' => $this->group->id]))->json('data'))->firstWhere('id', $submitted);

    expect($row['end_date'])->toBe('2026-05-18')->and($row['end_date_is_expected'])->toBeFalse();

    $this->group->groupSetting()->update(['allow_multiple_loans' => true]);
    $rejected = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'loan_product_id' => $this->product->id, 'requested_amount' => 50000, 'purpose' => 'Other',
    ])->json('data.id');
    $this->actingAs($this->chair)->patchJson(route('loan-applications.reject', $rejected), ['rejection_reason' => 'No'])->assertOk()->assertJsonPath('data.end_date', null);
});
