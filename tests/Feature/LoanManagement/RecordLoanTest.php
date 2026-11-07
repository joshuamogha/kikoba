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
});

beforeEach(function () {
    $this->product = createLoanProductFor($this->group);
});

/**
 * @return array<string, mixed>
 */
function recordLoanPayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_member_id' => $test->plainMember->id,
        'loan_product_id' => $test->product->id,
        'requested_amount' => 100000,
        'duration_months' => 3,
        'purpose' => 'Opening balance from the paper book',
        'application_date' => now()->subMonths(2)->toDateString(),
        'disbursement_date' => now()->subMonths(2)->addDays(5)->toDateString(),
        ...$overrides,
    ];
}

test('an existing loan is recorded in one step as application, guarantors and loan', function () {
    $response = $this->actingAs($this->treasurer)->postJson(route('loan-applications.record'), recordLoanPayload($this, [
        'guarantors' => [
            ['group_member_id' => $this->friendMember->id, 'guaranteed_amount' => 50000],
            ['group_member_id' => $this->treasurerMember->id, 'guaranteed_amount' => 50000],
        ],
    ]))->assertCreated();

    $application = $this->group->loanApplications()->sole();
    $loan = $this->group->loans()->sole();

    expect($response->json('data.loan_number'))->toBe($loan->loan_number)
        ->and($application->status)->toBe('approved')
        ->and($application->application_date->toDateString())->toBe(now()->subMonths(2)->toDateString())
        ->and((float) $application->approved_amount)->toBe(100000.0)
        ->and($application->loanGuarantors()->where('status', 'accepted')->count())->toBe(2)
        ->and($loan->loan_application_id)->toBe($application->id)
        ->and($loan->disbursement_date->toDateString())->toBe(now()->subMonths(2)->addDays(5)->toDateString())
        ->and($loan->loanInstallments)->toHaveCount(3)
        ->and((float) $loan->total_payable)->toBe(115000.0)
        ->and($loan->financialCycle->start_date->year)->toBe(now()->subMonths(2)->addDays(5)->year);
});

test('guarantors are optional and the approved amount can be lower than requested', function () {
    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this, ['approved_amount' => 80000]))->assertCreated();

    expect($this->group->loanApplications()->sole()->loanGuarantors()->count())->toBe(0)
        ->and((float) $this->group->loans()->sole()->principal_amount)->toBe(80000.0);
});

test('recording history skips the eligibility checks of a new application', function () {
    $this->product->update(['maximum_savings_multiplier' => 3, 'guarantors_required' => 2, 'maximum_amount' => 50000]);

    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this))->assertCreated();

    expect($this->group->loans()->count())->toBe(2);
});

test('the dates, amounts, members and guarantors are validated', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreign = addMemberTo($other, User::factory()->create());

    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this, [
        'disbursement_date' => now()->addDay()->toDateString(),
        'approved_amount' => 200000,
        'group_member_id' => $foreign->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['disbursement_date', 'approved_amount', 'group_member_id']);

    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this, [
        'application_date' => now()->toDateString(),
        'disbursement_date' => now()->subDay()->toDateString(),
    ]))->assertUnprocessable()->assertJsonValidationErrors('application_date');

    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this, [
        'guarantors' => [['group_member_id' => $this->plainMember->id, 'guaranteed_amount' => 1000]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('guarantors.0.group_member_id');

    $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this, [
        'guarantors' => [['group_member_id' => $this->friendMember->id, 'guaranteed_amount' => 1000], ['group_member_id' => $this->friendMember->id, 'guaranteed_amount' => 1000]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('guarantors.0.group_member_id');

    expect($this->group->loans()->count())->toBe(0);
});

test('only leaders and the treasurer can record a loan', function () {
    $this->actingAs($this->plain)->postJson(route('loan-applications.record'), recordLoanPayload($this))->assertForbidden();
    $this->actingAs($this->outsider)->postJson(route('loan-applications.record'), recordLoanPayload($this))->assertForbidden();
});

test('a recorded loan can be repaid like any other', function () {
    $loanId = $this->actingAs($this->chair)->postJson(route('loan-applications.record'), recordLoanPayload($this))->json('data.loan_id');
    $repayment = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), [
        'group_id' => $this->group->id, 'loan_id' => $loanId, 'amount' => 40000, 'payment_date' => now()->toDateString(), 'payment_method_id' => $this->group->paymentMethods()->value('id'),
    ])->assertCreated()->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $repayment))->assertOk();

    expect((float) $this->group->loans()->sole()->outstanding_balance)->toBe(75000.0);
});
