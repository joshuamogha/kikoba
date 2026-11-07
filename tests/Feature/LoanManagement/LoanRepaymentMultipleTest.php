<?php

use App\Actions\LoanManagement\RecordExistingLoan;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->product = createLoanProductFor($this->group);

    [, $this->loan] = app(RecordExistingLoan::class)->handle(
        $this->group, $this->product, $this->chair,
        ['group_member_id' => $this->plainMember->id, 'requested_amount' => 100000, 'duration_months' => 3, 'purpose' => 'Test'],
        Carbon::parse('2026-01-10'), Carbon::parse('2026-01-10'),
    );
});

function multiPayload($test, array $payments, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'loan_id' => $test->loan->id,
        'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'payments' => $payments,
        ...$overrides,
    ];
}

test('several payments with their own dates and amounts are recorded for one loan', function () {
    $response = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), multiPayload($this, [
        ['payment_date' => '2026-03-01', 'amount' => 30000, 'external_reference' => 'RCT-3'],
        ['payment_date' => '2026-02-01', 'amount' => 20000, 'external_reference' => 'RCT-2'],
        ['payment_date' => '2026-02-15', 'amount' => 10000],
    ]))->assertCreated()->assertJsonCount(3, 'records')->assertJsonPath('message', '3 repayments recorded successfully.');

    $repayments = LoanRepayment::query()->where('loan_id', $this->loan->id)->orderBy('id')->get();

    expect($repayments->pluck('payment_date')->map->toDateString()->all())->toBe(['2026-02-01', '2026-02-15', '2026-03-01'])
        ->and($repayments->pluck('amount')->map(fn ($amount) => (float) $amount)->all())->toBe([20000.0, 10000.0, 30000.0])
        ->and($repayments->pluck('external_reference')->all())->toBe(['RCT-2', null, 'RCT-3'])
        ->and($repayments->pluck('status')->unique()->all())->toBe(['pending'])
        ->and($repayments->every(fn ($repayment) => $repayment->group_member_id === $this->plainMember->id))->toBeTrue()
        ->and($response->json('data.id'))->toBe($repayments[0]->id);

    foreach ($repayments as $repayment) {
        $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $repayment))->assertOk();
    }

    expect((float) $this->loan->fresh()->amount_paid)->toBe(60000.0)->and((float) $this->loan->fresh()->outstanding_balance)->toBe(55000.0);
});

test('the payments together cannot exceed the outstanding balance and nothing is saved', function () {
    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), multiPayload($this, [
        ['payment_date' => '2026-02-01', 'amount' => 100000],
        ['payment_date' => '2026-02-02', 'amount' => 15001],
    ]))->assertUnprocessable()->assertJsonValidationErrors('payments');

    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), multiPayload($this, [
        ['payment_date' => '2026-02-01', 'amount' => 100000],
        ['payment_date' => '2026-02-02', 'amount' => 15000],
    ]))->assertCreated();

    expect(LoanRepayment::query()->count())->toBe(2);
});

test('each payment needs a valid date and amount, not before disbursement and not in the future', function () {
    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), multiPayload($this, [
        ['payment_date' => '2026-02-01', 'amount' => 1000],
        ['payment_date' => '2026-01-05', 'amount' => 1000],
        ['payment_date' => now()->addDay()->toDateString(), 'amount' => 1000],
        ['payment_date' => '2026-02-03', 'amount' => 0],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['payments.2.payment_date', 'payments.3.amount']);

    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), multiPayload($this, [
        ['payment_date' => '2026-02-01', 'amount' => 1000],
        ['payment_date' => '2026-01-05', 'amount' => 1000],
    ]))->assertUnprocessable()->assertJsonValidationErrors('payments.1.payment_date');

    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), multiPayload($this, []))->assertUnprocessable()->assertJsonValidationErrors('payments');

    expect(LoanRepayment::query()->count())->toBe(0);
});

test('the single payment form still works, only leaders and the treasurer record payments, and the loan list shows the balance', function () {
    $single = ['group_id' => $this->group->id, 'loan_id' => $this->loan->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'), 'payment_date' => '2026-02-01', 'amount' => 5000];

    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), $single)->assertCreated()->assertJsonCount(1, 'records')->assertJsonPath('message', 'Repayment recorded successfully.');
    $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), [...$single, 'amount' => 115001])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->actingAs($this->plain)->postJson(route('loan-repayments.store'), multiPayload($this, [['payment_date' => '2026-02-01', 'amount' => 1000]]))->assertForbidden();

    $loan = collect($this->actingAs($this->chair)->getJson(route('loan-repayments.index', ['group_id' => $this->group->id]))->json('options.loans'))->firstWhere('value', $this->loan->id);

    expect($loan['balance'])->toBe(115000)->and($loan['disbursed'])->toBe('2026-01-10');
});
