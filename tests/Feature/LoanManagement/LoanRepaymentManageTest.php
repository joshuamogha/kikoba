<?php

use App\Actions\LoanManagement\RecordExistingLoan;
use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\PaymentTransaction;
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

/**
 * Record a repayment on the loan and approve it (or leave it pending).
 */
function repay($test, float $amount, string $date, bool $approve = true): int
{
    $id = $test->actingAs($test->chair)->postJson(route('loan-repayments.store'), [
        'group_id' => $test->group->id, 'loan_id' => $test->loan->id, 'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'payment_date' => $date, 'amount' => $amount,
    ])->assertCreated()->json('data.id');

    if ($approve) {
        $test->actingAs($test->chair)->patchJson(route('loan-repayments.approve', $id))->assertOk();
    }

    return $id;
}

function editRepayment($test, int $id, array $overrides = [], ?User $as = null)
{
    $repayment = LoanRepayment::query()->findOrFail($id);

    return $test->actingAs($as ?? $test->chair)->putJson(route('loan-repayments.update', $id), [
        'group_id' => $test->group->id,
        'payment_method_id' => $repayment->payment_method_id,
        'payment_date' => $repayment->payment_date->toDateString(),
        'amount' => (float) $repayment->amount,
        'external_reference' => $repayment->external_reference,
        ...$overrides,
    ]);
}

test('editing an approved repayment re-applies the payments to the installments and updates the loan and its transaction', function () {
    $first = repay($this, 20000, '2026-02-10');
    $second = repay($this, 50000, '2026-03-01');

    editRepayment($this, $second, ['amount' => 30000, 'external_reference' => 'RCT-9'])->assertOk()->assertJsonPath('data.amount', 30000);

    $loan = $this->loan->fresh();
    $installments = $loan->loanInstallments()->orderBy('installment_number')->get();
    $repayment = LoanRepayment::query()->findOrFail($second);

    expect((float) $loan->amount_paid)->toBe(50000.0)
        ->and((float) $loan->outstanding_balance)->toBe(65000.0)
        ->and($loan->status)->toBe('active')
        ->and($installments[0]->status)->toBe('paid')
        ->and($installments[1]->status)->toBe('partial')
        ->and((float) $installments[1]->total_paid)->toBe(11666.67)
        ->and($installments[2]->status)->toBe('pending')
        ->and(round($repayment->principal_amount + $repayment->interest_amount + $repayment->penalty_amount, 2))->toBe(30000.0)
        ->and($repayment->external_reference)->toBe('RCT-9');

    $transaction = PaymentTransaction::query()->where('reference_id', $second)->where('transaction_type', 'loan_repayment')->sole();

    expect((float) $transaction->amount)->toBe(30000.0)->and($transaction->provider_transaction_id)->toBe('RCT-9')
        ->and(PaymentTransaction::query()->where('reference_id', $first)->count())->toBe(1);
});

test('changing the date of a repayment changes which payment is applied first', function () {
    $first = repay($this, 10000, '2026-02-10');
    repay($this, 40000, '2026-03-01');

    editRepayment($this, $first, ['payment_date' => '2026-03-15'])->assertOk();

    $order = LoanRepayment::query()->orderBy('payment_date')->pluck('amount')->map(fn ($amount) => (float) $amount)->all();

    expect($order)->toBe([40000.0, 10000.0])
        ->and((float) $this->loan->fresh()->amount_paid)->toBe(50000.0)
        ->and((float) $this->loan->fresh()->outstanding_balance)->toBe(65000.0);
});

test('an edit cannot push the repayments over what the loan has to pay, or before disbursement or into the future', function () {
    repay($this, 50000, '2026-02-10');
    $second = repay($this, 40000, '2026-03-01');

    editRepayment($this, $second, ['amount' => 65001])->assertUnprocessable()->assertJsonValidationErrors('amount');
    editRepayment($this, $second, ['amount' => 65000])->assertOk();
    editRepayment($this, $second, ['payment_date' => '2026-01-05'])->assertUnprocessable()->assertJsonValidationErrors('payment_date');
    editRepayment($this, $second, ['payment_date' => now()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('payment_date');
    editRepayment($this, $second, ['amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');

    expect((float) $this->loan->fresh()->outstanding_balance)->toBe(0.0)->and($this->loan->fresh()->status)->toBe('completed');
});

test('deleting an approved repayment removes its transaction and recalculates the loan', function () {
    $first = repay($this, 20000, '2026-02-10');
    $second = repay($this, 95000, '2026-03-01');

    expect($this->loan->fresh()->status)->toBe('completed');

    $this->actingAs($this->chair)->deleteJson(route('loan-repayments.destroy', $second))->assertOk();

    $loan = $this->loan->fresh();

    expect(LoanRepayment::query()->count())->toBe(1)
        ->and(PaymentTransaction::query()->where('reference_id', $second)->count())->toBe(0)
        ->and(PaymentTransaction::query()->where('reference_id', $first)->count())->toBe(1)
        ->and($loan->status)->toBe('active')
        ->and((float) $loan->amount_paid)->toBe(20000.0)
        ->and((float) $loan->outstanding_balance)->toBe(95000.0)
        ->and($loan->loanInstallments()->where('status', 'paid')->count())->toBe(0);

    $this->actingAs($this->chair)->deleteJson(route('loan-repayments.destroy', $first))->assertOk();

    expect((float) $this->loan->fresh()->outstanding_balance)->toBe(115000.0)->and($this->loan->fresh()->loanInstallments()->where('status', '!=', 'pending')->count())->toBe(0);
});

test('a pending repayment can be edited against the outstanding balance and deleted without touching the loan', function () {
    $id = repay($this, 10000, '2026-02-10', approve: false);

    editRepayment($this, $id, ['amount' => 20000])->assertOk()->assertJsonPath('data.status', 'pending');
    editRepayment($this, $id, ['amount' => 115001])->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->actingAs($this->chair)->deleteJson(route('loan-repayments.destroy', $id))->assertOk();

    expect(LoanRepayment::query()->count())->toBe(0)->and((float) $this->loan->fresh()->outstanding_balance)->toBe(115000.0);
});

test('penalties added to installments stay when a repayment is changed or deleted', function () {
    $installment = $this->loan->loanInstallments()->orderBy('installment_number')->first();
    $installment->update(['status' => 'overdue', 'penalty_due' => 2000, 'total_due' => $installment->total_due + 2000]);
    $this->loan->update(['status' => 'overdue', 'penalty_amount' => 2000, 'total_payable' => 117000, 'outstanding_balance' => 117000]);

    $id = repay($this, 40000, '2026-02-10');

    expect((float) $installment->fresh()->penalty_paid)->toBe(2000.0);

    editRepayment($this, $id, ['amount' => 30000])->assertOk();
    expect((float) $this->loan->fresh()->outstanding_balance)->toBe(87000.0);

    $this->actingAs($this->chair)->deleteJson(route('loan-repayments.destroy', $id))->assertOk();

    $loan = Loan::query()->findOrFail($this->loan->id);

    expect((float) $loan->outstanding_balance)->toBe(117000.0)
        ->and($loan->status)->toBe('overdue')
        ->and($installment->fresh()->status)->toBe('overdue')
        ->and((float) $installment->fresh()->penalty_paid)->toBe(0.0)
        ->and((float) $installment->fresh()->penalty_due)->toBe(2000.0);
});

test('only leaders and the treasurer can edit or delete, and only within the repayment\'s own group', function () {
    $id = repay($this, 10000, '2026-02-10');
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);

    editRepayment($this, $id, ['amount' => 5000], $this->plain)->assertForbidden();
    $this->actingAs($this->plain)->deleteJson(route('loan-repayments.destroy', $id))->assertForbidden();
    $this->actingAs($this->chair)->putJson(route('loan-repayments.update', $id), [
        'group_id' => $other->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'), 'payment_date' => '2026-02-10', 'amount' => 5000,
    ])->assertNotFound();

    expect((float) LoanRepayment::query()->findOrFail($id)->amount)->toBe(10000.0)->and(LoanRepayment::query()->count())->toBe(1);
});
