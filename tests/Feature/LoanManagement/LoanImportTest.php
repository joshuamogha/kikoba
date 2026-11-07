<?php

use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friendMember = addMemberTo($this->group, User::factory()->create());
    $this->thirdMember = addMemberTo($this->group, User::factory()->create());
    $this->outsider = User::factory()->create();
    $this->product = createLoanProductFor($this->group, ['code' => 'EMG', 'name' => 'Emergency']);
});

const LOAN_HEADER = 'phone_number,loan_product,amount,application_date,disbursement_date,purpose,duration_months';

function importLoans($test, string $rows, bool $guarantors = false, ?User $as = null, string $header = LOAN_HEADER)
{
    return $test->actingAs($as ?? $test->chair)->postJson(route('loan-applications.import'), [
        'group_id' => $test->group->id,
        'include_guarantors' => $guarantors,
        'file' => UploadedFile::fake()->createWithContent('loans.csv', "{$header}\n{$rows}"),
    ]);
}

test('the template has guarantor columns only when asked for, and only leaders and the treasurer download it', function () {
    $url = fn (array $query) => route('loan-applications.template', ['group_id' => $this->group->id, ...$query]);
    $headings = fn ($response) => explode(',', trim(explode("\n", str_replace("\r", '', $response->streamedContent()))[0]));

    $without = $this->actingAs($this->treasurer)->get($url(['include_guarantors' => 0]))->assertOk()->assertDownload('loans-import-template.csv');
    $with = $this->actingAs($this->chair)->get($url(['include_guarantors' => 1]));

    expect($headings($without))->toBe(explode(',', LOAN_HEADER))
        ->and($headings($with))->toHaveCount(13)->and($headings($with)[7])->toBe('guarantor1_phone')
        ->and($without->streamedContent())->toContain('EMG');

    $this->actingAs($this->plain)->get($url([]))->assertForbidden();
});

test('a row goes through every step: approved application, disbursed loan and installment schedule', function () {
    importLoans($this, "{$this->plainMember->phone_number},EMG,600000,05/01/2026,10/01/2026 09:00:00,School fees,6\n")
        ->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped', []);

    $application = LoanApplication::query()->sole();
    $loan = Loan::query()->sole();

    expect($application->status)->toBe('approved')
        ->and($application->application_number)->toBe(sprintf('LA-2026-%06d', $application->id))
        ->and($application->application_date->toDateString())->toBe('2026-01-05')
        ->and((float) $application->approved_amount)->toBe(600000.0)
        ->and($application->duration_months)->toBe(6)
        ->and($application->purpose)->toBe('School fees')
        ->and($application->approved_by)->toBe($this->chair->id)
        ->and($loan->loan_application_id)->toBe($application->id)
        ->and($loan->group_member_id)->toBe($this->plainMember->id)
        ->and($loan->status)->toBe('active')
        ->and($loan->disbursement_date->toDateString())->toBe('2026-01-10')
        ->and((float) $loan->principal_amount)->toBe(600000.0)
        ->and($loan->loanInstallments()->count())->toBe(6)
        ->and($application->loanGuarantors()->count())->toBe(0);
});

test('guarantors are recorded as accepted when included and ignored when not', function () {
    $row = "{$this->plainMember->phone_number},EMG,600000,2026-01-05,2026-01-10,Fees,6,{$this->friendMember->phone_number},300000,{$this->thirdMember->phone_number},200000,,\n";
    $header = LOAN_HEADER.',guarantor1_phone,guarantor1_amount,guarantor2_phone,guarantor2_amount,guarantor3_phone,guarantor3_amount';

    importLoans($this, $row, guarantors: true, header: $header)->assertOk()->assertJsonPath('data.imported', 1);

    $guarantors = LoanApplication::query()->sole()->loanGuarantors()->orderBy('id')->get();

    expect($guarantors)->toHaveCount(2)
        ->and($guarantors->pluck('status')->unique()->all())->toBe(['accepted'])
        ->and($guarantors->pluck('group_member_id')->all())->toBe([$this->friendMember->id, $this->thirdMember->id])
        ->and((float) $guarantors[0]->guaranteed_amount)->toBe(300000.0);

    $other = "{$this->friendMember->phone_number},EMG,400000,2026-01-05,2026-01-10,Fees,6,{$this->plainMember->phone_number},100000,,,,\n";

    importLoans($this, $other, guarantors: false, header: $header)->assertOk()->assertJsonPath('data.imported', 1);

    expect(LoanApplication::query()->where('group_member_id', $this->friendMember->id)->sole()->loanGuarantors()->count())->toBe(0);
});

test('the term comes from the amount ranges, or from the file when the product has none', function () {
    $ranged = createLoanProductFor($this->group, ['code' => 'RNG', 'name' => 'Ranged', 'repayment_mode' => 'lump_sum']);
    $ranged->loanProductRanges()->create(['minimum_amount' => 0, 'maximum_amount' => 500000, 'duration_months' => 2]);
    $ranged->loanProductRanges()->create(['minimum_amount' => 500001, 'maximum_amount' => 1000000, 'duration_months' => 4]);
    $ranged->syncDurationsFromRanges();

    $response = importLoans($this, "{$this->plainMember->phone_number},RNG,800000,2026-01-05,2026-01-10,Fees,\n{$this->friendMember->phone_number},RNG,2000000,2026-01-05,2026-01-10,Fees,3\n{$this->thirdMember->phone_number},EMG,100000,2026-01-05,2026-01-10,Fees,\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    $skipped = collect($response->json('data.skipped'))->keyBy('row');

    expect($skipped[3]['reason'])->toContain('No loan range')->and($skipped[4]['reason'])->toContain('duration');

    $loan = Loan::query()->sole();

    expect($loan->group_member_id)->toBe($this->plainMember->id)
        ->and($loan->loanApplication->duration_months)->toBe(4)
        ->and($loan->loanInstallments()->count())->toBe(1)
        ->and($loan->maturity_date->toDateString())->toBe('2026-05-10');
});

test('bad rows are skipped with the reason while good rows import', function () {
    $phone = $this->plainMember->phone_number;
    $future = now()->addDay()->toDateString();
    $this->friendMember->update(['membership_status' => 'suspended']);

    $response = importLoans($this, "0000,EMG,1000,2026-01-05,2026-01-10,Fees,6\n{$this->friendMember->phone_number},EMG,1000,2026-01-05,2026-01-10,Fees,6\n{$phone},NOPE,1000,2026-01-05,2026-01-10,Fees,6\n{$phone},EMG,abc,2026-01-05,2026-01-10,Fees,6\n{$phone},EMG,1000,2026-01-12,2026-01-10,Fees,6\n{$phone},EMG,1000,2026-01-05,{$future},Fees,6\n{$phone},EMG,1000,2026-01-05,2026-02-30,Fees,6\n{$phone},EMG,1000,2026-01-05,2026-01-10,,6\n{$phone},EMG,1000,2026-01-05,2026-01-10,Fees,0\n{$phone},EMG,1000,2026-01-05,2026-01-10,Fees,6\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    $skipped = collect($response->json('data.skipped'))->keyBy('row');

    expect($skipped->keys()->all())->toBe([2, 3, 4, 5, 6, 7, 8, 9, 10])
        ->and($skipped[3]['reason'])->toContain('not active')
        ->and($skipped[4]['reason'])->toContain('code')
        ->and($skipped[9]['reason'])->toContain('purpose')
        ->and(Loan::query()->count())->toBe(1);
});

test('guarantor mistakes skip the row without creating anything', function () {
    $phone = $this->plainMember->phone_number;
    $header = LOAN_HEADER.',guarantor1_phone,guarantor1_amount,guarantor2_phone,guarantor2_amount';
    $base = "{$phone},EMG,100000,2026-01-05,2026-01-10,Fees,6";

    $response = importLoans($this, "{$base},{$this->friendMember->phone_number},,,\n{$base},{$phone},50000,,\n{$base},0000,50000,,\n{$base},{$this->friendMember->phone_number},50000,{$this->friendMember->phone_number},20000\n{$base},{$this->friendMember->phone_number},abc,,\n", guarantors: true, header: $header)
        ->assertOk()->assertJsonPath('data.imported', 0);

    expect(collect($response->json('data.skipped'))->pluck('reason')->all())->toBe([
        'Guarantor 1 needs both a phone number and an amount.',
        'A member cannot guarantee their own loan.',
        'Guarantor 1 is not an active member of this group.',
        'Guarantor 2 is listed twice.',
        'The amount of guarantor 1 must be a positive number.',
    ])->and(LoanApplication::query()->count())->toBe(0);
});

test('uploading the same file again records nothing new', function () {
    $rows = "{$this->plainMember->phone_number},EMG,600000,2026-01-05,2026-01-10,Fees,6\n{$this->friendMember->phone_number},EMG,300000,2026-01-05,2026-01-12,Fees,3\n";

    importLoans($this, $rows)->assertJsonPath('data.imported', 2);
    importLoans($this, $rows)->assertJsonPath('data.imported', 0)->assertJsonCount(2, 'data.skipped');
    importLoans($this, "{$this->plainMember->phone_number},EMG,600000,2026-01-05,2026-02-10,Fees,6\n")->assertJsonPath('data.imported', 1);

    expect(Loan::query()->count())->toBe(3)->and(LoanApplication::query()->count())->toBe(3);
});

test('an Excel file works and the file needs the right headings', function () {
    $file = makeXlsx([
        ['phone_number', 'loan_product', 'amount', 'application_date', 'disbursement_date', 'purpose', 'duration_months'],
        [(int) ltrim($this->plainMember->phone_number, '0'), 'emg', 600000, '05/01/2026', '10/01/2026 09:00:00', 'Fees', 6],
    ], 'loans.xlsx');

    $this->actingAs($this->chair)->postJson(route('loan-applications.import'), ['group_id' => $this->group->id, 'file' => $file])->assertOk()->assertJsonPath('data.imported', 1);
    importLoans($this, "{$this->plainMember->phone_number},EMG,1000\n", header: 'phone_number,loan_product,amount')->assertUnprocessable()->assertJsonValidationErrors('file');
});

test('only leaders and the treasurer can import loans', function () {
    $row = "{$this->plainMember->phone_number},EMG,600000,2026-01-05,2026-01-10,Fees,6\n";

    importLoans($this, $row, as: $this->plain)->assertForbidden();
    importLoans($this, $row, as: $this->outsider)->assertForbidden();
    importLoans($this, $row, as: $this->treasurer)->assertOk()->assertJsonPath('data.imported', 1);
});
