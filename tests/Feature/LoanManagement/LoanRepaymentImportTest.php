<?php

use App\Actions\LoanManagement\RecordExistingLoan;
use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friendMember = addMemberTo($this->group, User::factory()->create());
    $this->outsider = User::factory()->create();
    $this->product = createLoanProductFor($this->group);
});

/**
 * A 100,000 loan over 3 months at 5% a month, disbursed on 2026-01-10: three installments of 38,333.33, total payable 115,000.
 */
function pastLoan($test, $member = null, string $disbursed = '2026-01-10'): Loan
{
    [, $loan] = app(RecordExistingLoan::class)->handle(
        $test->group, $test->product, $test->chair,
        ['group_member_id' => ($member ?? $test->plainMember)->id, 'requested_amount' => 100000, 'duration_months' => 3, 'purpose' => 'Test'],
        Carbon::parse($disbursed), Carbon::parse($disbursed),
    );

    return $loan;
}

function importRepayments($test, string $rows, ?User $as = null, string $header = 'loan_number,amount,payment_date,receipt', array $extra = [])
{
    return $test->actingAs($as ?? $test->chair)->postJson(route('loan-repayments.import'), [
        'group_id' => $test->group->id,
        'file' => UploadedFile::fake()->createWithContent('repayments.csv', "{$header}\n{$rows}"),
        ...$extra,
    ]);
}

test('the template is pre-filled with the open loans and only leaders and the treasurer download it', function () {
    $open = pastLoan($this);
    $done = pastLoan($this, $this->friendMember);
    $done->update(['status' => 'completed']);

    $response = $this->actingAs($this->treasurer)->get(route('loan-repayments.template', ['group_id' => $this->group->id]))->assertOk()->assertDownload('loan-repayments-import-template.csv');
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));

    expect($lines[0])->toBe('loan_number,amount,payment_date,receipt,member,outstanding_balance')
        ->and($lines)->toHaveCount(2)
        ->and($lines[1])->toStartWith($open->loan_number.',,,,')->toContain($this->plainMember->name)->toEndWith('115000.00');

    $this->actingAs($this->plain)->get(route('loan-repayments.template', ['group_id' => $this->group->id]))->assertForbidden();
});

test('repayments are applied in date order to the oldest installments, as approved and logged payments', function () {
    $loan = pastLoan($this);

    importRepayments($this, "{$loan->loan_number},50000,01/03/2026 10:15:00,RCT-2\n{$loan->loan_number},20000,10/02/2026,RCT-1\n")
        ->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', []);

    $loan->refresh();
    $repayments = LoanRepayment::query()->orderBy('payment_date')->get();
    $installments = $loan->loanInstallments()->orderBy('installment_number')->get();

    expect($repayments->pluck('status')->unique()->all())->toBe(['approved'])
        ->and($repayments->pluck('external_reference')->all())->toBe(['RCT-1', 'RCT-2'])
        ->and($repayments[1]->created_at->format('Y-m-d H:i:s'))->toBe('2026-03-01 10:15:00')
        ->and($repayments[1]->repayment_number)->toBe(sprintf('LR-2026-%06d', $repayments[1]->id))
        ->and($repayments->every(fn ($repayment) => round($repayment->principal_amount + $repayment->interest_amount + $repayment->penalty_amount, 2) === (float) $repayment->amount))->toBeTrue()
        ->and((float) $loan->amount_paid)->toBe(70000.0)
        ->and((float) $loan->outstanding_balance)->toBe(45000.0)
        ->and($loan->status)->toBe('active')
        ->and($installments[0]->status)->toBe('paid')
        ->and($installments[1]->status)->toBe('partial')
        ->and($installments[2]->status)->toBe('pending')
        ->and(PaymentTransaction::query()->where('transaction_type', 'loan_repayment')->count())->toBe(2);
});

test('a loan that is paid off becomes completed and takes no more repayments', function () {
    $loan = pastLoan($this);

    importRepayments($this, "{$loan->loan_number},115000,2026-03-01,RCT-1\n")->assertOk()->assertJsonPath('data.imported', 1);

    expect($loan->fresh()->status)->toBe('completed')->and((float) $loan->fresh()->outstanding_balance)->toBe(0.0);

    importRepayments($this, "{$loan->loan_number},1000,2026-03-02,RCT-2\n")->assertOk()->assertJsonPath('data.imported', 0)
        ->assertJsonPath('data.skipped.0.reason', 'This loan is already fully paid.');
});

test('bad rows are skipped with the reason while good rows import', function () {
    $loan = pastLoan($this);
    $foreign = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $future = now()->addDay()->toDateString();

    $response = importRepayments($this, "NOPE,1000,2026-02-01,\n{$loan->loan_number},abc,2026-02-01,\n{$loan->loan_number},0,2026-02-01,\n{$loan->loan_number},1000,,\n{$loan->loan_number},1000,{$future},\n{$loan->loan_number},1000,2026-02-30,\n{$loan->loan_number},1000,2026-01-01,\n{$loan->loan_number},116000,2026-02-01,\n{$loan->loan_number},1000,2026-02-01,\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    $skipped = collect($response->json('data.skipped'))->keyBy('row');

    expect($skipped->keys()->all())->toBe([2, 3, 4, 5, 6, 7, 8, 9])
        ->and($skipped[2]['reason'])->toContain('No loan of this group')
        ->and($skipped[8]['reason'])->toContain('before the loan was disbursed')
        ->and($skipped[9]['reason'])->toContain('more than the loan\'s outstanding balance of 115,000.00')
        ->and($skipped[3]['name'])->toBe($this->plainMember->name)
        ->and(LoanRepayment::query()->count())->toBe(1);
});

test('the outstanding balance is checked across the rows of one file, and a loan of another group is not found', function () {
    $loan = pastLoan($this);
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $this->product = createLoanProductFor($other);
    $this->group = $other;
    $this->chair = $other->creator ?? User::query()->find($other->created_by);
    $foreignLoan = pastLoan($this, addMemberTo($other, User::factory()->create()));
    $this->group = $loan->group;
    $this->chair = User::query()->find($loan->group->created_by);

    $response = importRepayments($this, "{$loan->loan_number},100000,2026-02-01,A\n{$loan->loan_number},20000,2026-02-02,B\n{$foreignLoan->loan_number},1000,2026-02-02,C\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    expect(collect($response->json('data.skipped'))->pluck('reason')->all())->toBe([
        'The amount is more than the loan\'s outstanding balance of 15,000.00.',
        'No loan of this group has that loan number.',
    ]);
});

test('a receipt identifies a payment so the same file can be uploaded again safely', function () {
    $loan = pastLoan($this);
    $rows = "{$loan->loan_number},10000,2026-02-01,RCT-1\n{$loan->loan_number},5000,2026-02-05,rct-1 \n{$loan->loan_number},5000,2026-02-06,RCT-2\n";

    importRepayments($this, $rows)->assertJsonPath('data.imported', 2)->assertJsonCount(1, 'data.skipped');
    importRepayments($this, $rows)->assertJsonPath('data.imported', 0)->assertJsonCount(3, 'data.skipped');

    expect(LoanRepayment::query()->count())->toBe(2)->and((float) $loan->fresh()->amount_paid)->toBe(15000.0);
});

test('without receipts the same loan, date and amount is a duplicate', function () {
    $loan = pastLoan($this);

    importRepayments($this, "{$loan->loan_number},10000,2026-02-01,\n{$loan->loan_number},10000,2026-02-01,\n{$loan->loan_number},5000,2026-02-01,\n{$loan->loan_number},10000,2026-02-02,\n")
        ->assertJsonPath('data.imported', 3)->assertJsonCount(1, 'data.skipped');

    expect(LoanRepayment::query()->count())->toBe(3);
});

test('blank rows of the pre-filled template are reported, not imported, and the payment method can be chosen', function () {
    $loan = pastLoan($this);
    $friendLoan = pastLoan($this, $this->friendMember);
    $method = $this->group->paymentMethods()->where('code', 'MPESA')->first();

    $response = importRepayments($this, "{$loan->loan_number},,,,{$this->plainMember->name},115000\n{$friendLoan->loan_number},20000,2026-02-01,,Friend,115000\n", header: 'loan_number,amount,payment_date,receipt,member,outstanding_balance', extra: ['payment_method_id' => $method->id])
        ->assertOk()->assertJsonPath('data.imported', 1);

    expect($response->json('data.skipped.0.reason'))->toContain('No amount and date')
        ->and(LoanRepayment::query()->sole()->payment_method_id)->toBe($method->id);
});

test('an Excel file works, the file needs the right headings and only leaders and the treasurer can import', function () {
    $loan = pastLoan($this);
    $file = makeXlsx([['loan_number', 'amount', 'payment_date', 'receipt'], [strtolower($loan->loan_number), 10000, '01/02/2026 09:30:00', 'X-1']], 'repayments.xlsx');

    $this->actingAs($this->chair)->postJson(route('loan-repayments.import'), ['group_id' => $this->group->id, 'file' => $file])->assertOk()->assertJsonPath('data.imported', 1);

    importRepayments($this, "{$loan->loan_number},1000\n", header: 'loan_number,amount')->assertUnprocessable()->assertJsonValidationErrors('file');
    importRepayments($this, "{$loan->loan_number},1000,2026-02-02,\n", as: $this->plain)->assertForbidden();
    importRepayments($this, "{$loan->loan_number},1000,2026-02-02,\n", as: $this->outsider)->assertForbidden();
    importRepayments($this, "{$loan->loan_number},1000,2026-02-02,\n", as: $this->treasurer)->assertOk()->assertJsonPath('data.imported', 1);
});
