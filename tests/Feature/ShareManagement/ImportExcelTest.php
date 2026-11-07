<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->member = addMemberTo($this->group, User::factory()->create());
    $this->group->groupSetting()->update(['share_price' => 5000]);
});

function excelSerial(string $dateTime): float
{
    $date = Carbon::parse($dateTime);

    return $date->copy()->startOfDay()->diffInDays(Carbon::create(1899, 12, 30), true) + $date->secondsSinceMidnight() / 86400;
}

test('share dates written like 15/12/2025 08:32:47 are converted and kept as the created time', function () {
    $csv = "phone_number,amount,transaction_date\n{$this->member->phone_number},20000,15/12/2025 08:32:47\n{$this->member->phone_number},5000,2026-01-03\n";

    $this->actingAs($this->chair)->postJson(route('groups.shares.import', $this->group), ['file' => UploadedFile::fake()->createWithContent('shares.csv', $csv)])
        ->assertOk()->assertJsonPath('data.imported', 2);

    $first = $this->group->memberShareTransactions()->orderBy('id')->first();

    expect($first->transaction_date->toDateString())->toBe('2025-12-15')
        ->and($first->period_start->toDateString())->toBe('2025-12-01')
        ->and($first->created_at->format('Y-m-d H:i:s'))->toBe('2025-12-15 08:32:47')
        ->and($first->updated_at->format('Y-m-d H:i:s'))->toBe('2025-12-15 08:32:47');
});

test('an Excel file is accepted, with text dates, real date cells and a phone number that lost its zero', function () {
    $file = makeXlsx([
        ['phone_number', 'amount', 'transaction_date', 'reference'],
        [(int) ltrim($this->member->phone_number, '0'), 20000, '15/12/2025 08:32:47', 'text date'],
        [$this->member->phone_number, 10000, ['date' => excelSerial('2026-02-10 14:05:09')], 'real date cell'],
    ]);

    $this->actingAs($this->chair)->postJson(route('groups.shares.import', $this->group), ['file' => $file])
        ->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', []);

    $rows = $this->group->memberShareTransactions()->orderBy('id')->get();

    expect($rows[0]->number_of_shares)->toBe(4)
        ->and($rows[0]->created_at->format('Y-m-d H:i:s'))->toBe('2025-12-15 08:32:47')
        ->and($rows[1]->number_of_shares)->toBe(2)
        ->and($rows[1]->reference)->toBe('real date cell')
        ->and($rows[1]->created_at->format('Y-m-d H:i:s'))->toBe('2026-02-10 14:05:09')
        ->and($rows[1]->period_start->toDateString())->toBe('2026-02-01');
});

test('a broken Excel file is reported, not a server error', function () {
    $file = UploadedFile::fake()->createWithContent('broken.xlsx', 'this is not a zip');

    $this->actingAs($this->chair)->postJson(route('groups.shares.import', $this->group), ['file' => $file])->assertUnprocessable();
});

test('members can be imported from an Excel file too', function () {
    $file = makeXlsx([['name', 'phone_number'], ['Asha Juma', 755123456], ['Baraka Mushi', '0766 111 222']]);

    $this->actingAs($this->chair)->postJson(route('members.import'), ['group_id' => $this->group->id, 'file' => $file])
        ->assertOk()->assertJsonPath('data.imported', 2);

    expect($this->group->groupMembers()->where('phone_number', '0755123456')->exists())->toBeTrue();
});
