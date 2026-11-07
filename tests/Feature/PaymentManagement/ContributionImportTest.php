<?php

use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\PaymentTransaction;
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
    $this->outsider = User::factory()->create();

    $this->fund = createFundFor($this->group, ['name' => 'Akiba', 'code' => 'SAV']);
    $this->social = createFundFor($this->group, ['name' => 'Social', 'code' => 'SOC']);
});

function importContributions($test, string $rows, ?int $fundId = null, ?User $as = null, array $extra = [], string $header = 'phone_number,amount,transaction_date,receipt')
{
    return $test->actingAs($as ?? $test->chair)->postJson(route('contributions.import'), [
        'group_id' => $test->group->id,
        'group_fund_id' => $fundId ?? $test->fund->id,
        'file' => UploadedFile::fake()->createWithContent('contributions.csv', "{$header}\n{$rows}"),
        ...$extra,
    ]);
}

test('the template has the four columns and only leaders and the treasurer can download it', function () {
    $response = $this->actingAs($this->treasurer)->get(route('contributions.template', ['group_id' => $this->group->id]))->assertOk()->assertDownload('contributions-import-template.csv');
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));

    expect($lines[0])->toBe('phone_number,amount,transaction_date,receipt')->and($lines)->toHaveCount(3);

    $this->actingAs($this->plain)->get(route('contributions.template', ['group_id' => $this->group->id]))->assertForbidden();
});

test('each row becomes an approved contribution to the chosen fund in the month of its date', function () {
    importContributions($this, "{$this->plainMember->phone_number},20000,15/12/2025 08:32:47,RCT-1\n{$this->friendMember->phone_number},\"15,000\",2026-03-10,\n")
        ->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', []);

    $first = Contribution::query()->where('group_member_id', $this->plainMember->id)->sole();
    $second = Contribution::query()->where('group_member_id', $this->friendMember->id)->sole();

    expect($first->status)->toBe('approved')
        ->and((float) $first->total_amount)->toBe(20000.0)
        ->and($first->external_reference)->toBe('RCT-1')
        ->and($first->contribution_number)->toBe(sprintf('CON-2025-%06d', $first->id))
        ->and($first->created_at->format('Y-m-d H:i:s'))->toBe('2025-12-15 08:32:47')
        ->and($first->financialCycle->start_date->toDateString())->toBe('2025-01-01')
        ->and($first->contributionItems()->sole()->group_fund_id)->toBe($this->fund->id)
        ->and($first->contributionItems()->sole()->period_start->toDateString())->toBe('2025-12-01')
        ->and($first->paymentMethod->code)->toBe('CASH')
        ->and((float) $second->total_amount)->toBe(15000.0)
        ->and($second->external_reference)->toBeNull()
        ->and(PaymentTransaction::query()->where('transaction_type', 'contribution')->count())->toBe(2);

    $this->actingAs($this->chair)->getJson(route('contributions.index', ['group_id' => $this->group->id, 'group_fund_id' => $this->fund->id, 'month' => '2026-03']))
        ->assertJsonPath('totals.count', 1)->assertJsonPath('totals.amount', 15000);
});

test('the chosen fund and payment method are used, and an Excel file works', function () {
    $method = $this->group->paymentMethods()->where('code', 'MPESA')->first();
    $file = makeXlsx([['phone_number', 'amount', 'transaction_date', 'receipt'], [(int) ltrim($this->plainMember->phone_number, '0'), 5000, '2026-02-05', 'X-1']], 'contributions.xlsx');

    $this->actingAs($this->chair)->postJson(route('contributions.import'), [
        'group_id' => $this->group->id, 'group_fund_id' => $this->social->id, 'payment_method_id' => $method->id, 'file' => $file,
    ])->assertOk()->assertJsonPath('data.imported', 1);

    $contribution = Contribution::query()->sole();

    expect($contribution->contributionItems()->sole()->group_fund_id)->toBe($this->social->id)->and($contribution->payment_method_id)->toBe($method->id);
});

test('bad rows are skipped with the reason while good rows import', function () {
    $phone = $this->plainMember->phone_number;
    $future = now()->addDay()->toDateString();
    $this->friendMember->update(['membership_status' => 'suspended']);

    $response = importContributions($this, "0000,5000,2026-01-10,\n{$this->friendMember->phone_number},5000,2026-01-10,\n{$phone},abc,2026-01-10,\n{$phone},0,2026-01-10,\n{$phone},5000,,\n{$phone},5000,{$future},\n{$phone},5000,2026-02-30,\n{$phone},5000,2026-01-10,\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    $skipped = collect($response->json('data.skipped'));

    expect($skipped->pluck('row')->all())->toBe([2, 3, 4, 5, 6, 7, 8])
        ->and($skipped->firstWhere('row', 3)['reason'])->toContain('not active')
        ->and($skipped->firstWhere('row', 6)['reason'])->toContain('transaction date is required');
});

test('a receipt identifies a payment so the same file can be uploaded again safely', function () {
    $phone = $this->plainMember->phone_number;
    $rows = "{$phone},10000,2026-01-10,RCT-1\n{$phone},12000,2026-02-10,rct-1 \n{$this->friendMember->phone_number},10000,2026-01-10,RCT-2\n";

    importContributions($this, $rows)->assertJsonPath('data.imported', 2)->assertJsonCount(1, 'data.skipped');
    importContributions($this, $rows)->assertJsonPath('data.imported', 0)->assertJsonCount(3, 'data.skipped');

    expect(Contribution::query()->count())->toBe(2)->and(PaymentTransaction::query()->count())->toBe(2);

    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $otherFund = createFundFor($other, ['name' => 'Akiba', 'code' => 'SAV']);
    $stranger = addMemberTo($other, User::factory()->create());

    $this->actingAs($other->creator ?? User::query()->find($other->created_by))->postJson(route('contributions.import'), [
        'group_id' => $other->id, 'group_fund_id' => $otherFund->id,
        'file' => UploadedFile::fake()->createWithContent('c.csv', "phone_number,amount,transaction_date,receipt\n{$stranger->phone_number},10000,2026-01-10,RCT-1\n"),
    ])->assertOk()->assertJsonPath('data.imported', 1);
});

test('without receipts the same member, date, fund and amount is a duplicate', function () {
    $phone = $this->plainMember->phone_number;

    importContributions($this, "{$phone},10000,2026-01-10,\n{$phone},10000,2026-01-10,\n{$phone},5000,2026-01-10,\n{$phone},10000,2026-01-11,\n")
        ->assertJsonPath('data.imported', 3)->assertJsonCount(1, 'data.skipped');

    importContributions($this, "{$phone},10000,2026-01-10,\n", $this->social->id)->assertJsonPath('data.imported', 1);

    expect(Contribution::query()->count())->toBe(4);
});

test('the shares fund, inactive funds, other groups\' funds and wrong headings are refused', function () {
    $shares = createFundFor($this->group, ['fund_type_id' => $this->group->fundTypes()->where('code', 'SHR')->value('id'), 'name' => 'Hisa', 'code' => 'SHR']);
    $inactive = createFundFor($this->group, ['name' => 'Old', 'code' => 'OLD', 'status' => 'inactive']);
    $foreign = createFundFor(createGroupFor(User::factory()->create(), ['name' => 'Other']), ['name' => 'Foreign', 'code' => 'FOR']);
    $row = "{$this->plainMember->phone_number},5000,2026-01-10,\n";

    importContributions($this, $row, $shares->id)->assertUnprocessable()->assertJsonValidationErrors('group_fund_id');
    importContributions($this, $row, $inactive->id)->assertUnprocessable()->assertJsonValidationErrors('group_fund_id');
    importContributions($this, $row, $foreign->id)->assertUnprocessable()->assertJsonValidationErrors('group_fund_id');
    importContributions($this, "{$this->plainMember->phone_number},5000\n", header: 'phone_number,amount')->assertUnprocessable()->assertJsonValidationErrors('file');

    expect(Contribution::query()->count())->toBe(0);
});

test('only leaders and the treasurer can import', function () {
    $row = "{$this->plainMember->phone_number},5000,2026-01-10,\n";

    importContributions($this, $row, as: $this->plain)->assertForbidden();
    importContributions($this, $row, as: $this->outsider)->assertForbidden();
    importContributions($this, $row, as: $this->treasurer)->assertOk()->assertJsonPath('data.imported', 1);
});
