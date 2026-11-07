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
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();

    // 0 - 100,000 = 1 month, 100,001 - 500,000 = 3 months, over that = 6 months; one payment at the end.
    $this->product = createLoanProductFor($this->group, ['repayment_mode' => 'lump_sum', 'minimum_amount' => 1000, 'maximum_amount' => null]);
    $this->product->loanProductRanges()->createMany([
        ['minimum_amount' => 0, 'maximum_amount' => 100000, 'duration_months' => 1],
        ['minimum_amount' => 100001, 'maximum_amount' => 500000, 'duration_months' => 3],
        ['minimum_amount' => 500001, 'maximum_amount' => null, 'duration_months' => 6],
    ]);
});

/**
 * @return array<string, mixed>
 */
function rangedApplication($test, float $amount, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id, 'group_member_id' => $test->plainMember->id, 'loan_product_id' => $test->product->id,
        'requested_amount' => $amount, 'purpose' => 'Business', ...$overrides,
    ];
}

/**
 * Apply, approve and disburse a loan on the ranged product, returning the loan id.
 */
function rangedLoan($test, float $amount): int
{
    $application = $test->actingAs($test->plain)->postJson(route('loan-applications.store'), rangedApplication($test, $amount))->assertCreated()->json('data.id');
    $test->actingAs($test->chair)->patchJson(route('loan-applications.approve', $application), ['approved_amount' => $amount])->assertOk();

    return $test->actingAs($test->chair)->postJson(route('loans.store'), ['group_id' => $test->group->id, 'loan_application_id' => $application, 'disbursement_date' => now()->toDateString()])
        ->assertCreated()->json('data.id');
}

test('the product finds the term of the range that covers an amount', function () {
    expect($this->product->termFor(80000))->toBe(1)
        ->and($this->product->termFor(100000))->toBe(1)
        ->and($this->product->termFor(100001))->toBe(3)
        ->and($this->product->termFor(500000))->toBe(3)
        ->and($this->product->termFor(900000))->toBe(6)
        ->and($this->product->hasRanges())->toBeTrue();

    $this->product->loanProductRanges()->where('duration_months', 6)->delete();

    expect($this->product->termFor(900000))->toBeNull();
});

test('the term of an application comes from the amount and any duration sent is ignored', function () {
    foreach ([[80000, 1], [100000, 1], [100001, 3], [250000, 3], [900000, 6]] as [$amount, $months]) {
        $this->group->groupSetting()->update(['allow_multiple_loans' => true]);

        $this->actingAs($this->plain)->postJson(route('loan-applications.store'), rangedApplication($this, $amount, ['duration_months' => 12]))
            ->assertCreated()->assertJsonPath('data.duration_months', $months);
    }
});

test('an amount that no range covers is rejected', function () {
    $this->product->loanProductRanges()->where('duration_months', 6)->delete();

    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), rangedApplication($this, 900000))
        ->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
});

test('products without ranges still take the duration that was typed', function () {
    $plainProduct = createLoanProductFor($this->group);

    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), rangedApplication($this, 100000, ['loan_product_id' => $plainProduct->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('duration_months');
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), rangedApplication($this, 100000, ['loan_product_id' => $plainProduct->id, 'duration_months' => 7]))
        ->assertCreated()->assertJsonPath('data.duration_months', 7);
});

test('approving a lower amount moves the application to that range\'s term', function () {
    $id = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), rangedApplication($this, 250000))->assertCreated()->assertJsonPath('data.duration_months', 3)->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => 90000])
        ->assertOk()->assertJsonPath('data.duration_months', 1)->assertJsonPath('data.approved_amount', 90000);
});

test('the applications page offers each product with its ranges and mode', function () {
    $options = $this->actingAs($this->plain)->getJson(route('loan-applications.index', ['group_id' => $this->group->id]))->json('options.products');
    $ranged = collect($options)->firstWhere('value', $this->product->id);

    expect($ranged['repayment_mode'])->toBe('lump_sum')->and($ranged['ranges'])->toHaveCount(3)->and($ranged['ranges'][0])->toEqual(['minimum_amount' => 0, 'maximum_amount' => 100000, 'duration_months' => 1]);
});

test('a ranged loan has one installment due at the end of the term with the full interest', function () {
    $loanId = rangedLoan($this, 80000);

    $loan = $this->group->loans()->findOrFail($loanId);
    $installment = $loan->loanInstallments()->sole();

    expect($loan->loanInstallments()->count())->toBe(1)
        ->and($installment->due_date->toDateString())->toBe('2026-05-15')
        ->and((float) $installment->principal_due)->toBe(80000.0)
        ->and((float) $installment->interest_due)->toBe(4000.0)
        ->and((float) $installment->total_due)->toBe(84000.0)
        ->and((float) $loan->total_payable)->toBe(84000.0)
        ->and($loan->first_payment_date->toDateString())->toBe('2026-05-15')
        ->and($loan->maturity_date->toDateString())->toBe('2026-05-15');
});

test('a three month range charges three months of interest and is due once after three months', function () {
    $loan = $this->group->loans()->findOrFail(rangedLoan($this, 200000));
    $installment = $loan->loanInstallments()->sole();

    expect($installment->due_date->toDateString())->toBe('2026-07-15')
        ->and((float) $installment->interest_due)->toBe(30000.0)
        ->and((float) $loan->total_payable)->toBe(230000.0);
});

test('the grace period delays the single due date', function () {
    $this->product->update(['grace_period_days' => 10]);

    $loan = $this->group->loans()->findOrFail(rangedLoan($this, 80000));

    expect($loan->loanInstallments()->sole()->due_date->toDateString())->toBe('2026-05-25');
});

test('a ranged loan can be repaid in parts and completes with the last one', function () {
    $loanId = rangedLoan($this, 80000);

    foreach ([30000, 54000] as $amount) {
        $id = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), [
            'group_id' => $this->group->id, 'loan_id' => $loanId, 'amount' => $amount, 'payment_date' => now()->toDateString(), 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $id))->assertOk();

        if ($amount === 30000) {
            expect($this->group->loans()->findOrFail($loanId)->loanInstallments()->sole()->status)->toBe('partial');
        }
    }

    $loan = $this->group->loans()->findOrFail($loanId);

    expect($loan->status)->toBe('completed')->and((float) $loan->outstanding_balance)->toBe(0.0)->and($loan->loanInstallments()->sole()->status)->toBe('paid');
});

test('after the due date the single installment goes overdue and the penalty is added once', function () {
    $this->group->groupSetting()->update(['late_payment_penalty_value' => 2000]);
    $loanId = rangedLoan($this, 80000);

    $this->travelTo('2026-05-20 10:00:00');
    $this->artisan('loans:mark-overdue')->assertSuccessful();
    $this->artisan('loans:mark-overdue')->assertSuccessful();

    $loan = $this->group->loans()->findOrFail($loanId);

    expect($loan->status)->toBe('overdue')->and((float) $loan->penalty_amount)->toBe(2000.0)->and((float) $loan->outstanding_balance)->toBe(86000.0);
});

test('recording an existing loan on a ranged product derives the term from the amount', function () {
    $response = $this->actingAs($this->chair)->postJson(route('loan-applications.record'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'loan_product_id' => $this->product->id,
        'requested_amount' => 250000, 'duration_months' => 1, 'purpose' => 'Paper book', 'application_date' => '2026-03-01', 'disbursement_date' => '2026-03-02',
    ])->assertCreated();

    $loan = $this->group->loans()->findOrFail($response->json('data.loan_id'));

    expect($loan->loanInstallments()->count())->toBe(1)
        ->and($loan->maturity_date->toDateString())->toBe('2026-06-02')
        ->and($loan->loanApplication->duration_months)->toBe(3);

    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'loan_product_id' => $this->product->id,
        'requested_amount' => 250000, 'approved_amount' => 250000, 'purpose' => 'No duration sent', 'application_date' => '2026-03-01', 'disbursement_date' => '2026-03-02',
    ])->assertCreated();
});

test('installment products keep their installment schedule', function () {
    $this->product->update(['repayment_mode' => 'installments']);
    $this->product->loanProductRanges()->delete();

    $application = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), rangedApplication($this, 100000, ['duration_months' => 3]))->json('data.id');
    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $application), ['approved_amount' => 100000])->assertOk();
    $loan = $this->actingAs($this->chair)->postJson(route('loans.store'), ['group_id' => $this->group->id, 'loan_application_id' => $application, 'disbursement_date' => now()->toDateString()])->json('data.id');

    expect($this->group->loans()->findOrFail($loan)->loanInstallments()->count())->toBe(3);
});
