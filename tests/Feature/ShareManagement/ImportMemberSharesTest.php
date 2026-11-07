<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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
    $this->group->groupSetting()->update(['share_price' => 5000]);
});

function importShares($test, string $contents, ?User $as = null)
{
    return $test->actingAs($as ?? $test->chair)->postJson(route('groups.shares.import', $test->group), [
        'file' => UploadedFile::fake()->createWithContent('shares.csv', $contents),
    ]);
}

test('the template is a csv with the headings and sample rows', function () {
    $response = $this->actingAs($this->treasurer)->get(route('groups.shares.template', $this->group))->assertOk()->assertDownload('member-shares-import-template.csv');
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));

    expect($lines[0])->toBe('phone_number,amount,transaction_date,reference')->and($lines)->toHaveCount(3);

    $this->actingAs($this->plain)->get(route('groups.shares.template', $this->group))->assertForbidden();
});

test('shares come from the amount and the period from the transaction date', function () {
    importShares($this, "phone_number,amount,transaction_date,reference\n{$this->plainMember->phone_number},20000,2026-01-15,Opening balance\n{$this->friendMember->phone_number},\"10,000\",2026-03-31,\n")
        ->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', []);

    $first = $this->group->memberShareTransactions()->where('group_member_id', $this->plainMember->id)->sole();
    $second = $this->group->memberShareTransactions()->where('group_member_id', $this->friendMember->id)->sole();

    expect($first->number_of_shares)->toBe(4)
        ->and((float) $first->total_amount)->toBe(20000.0)
        ->and((float) $first->price_per_share)->toBe(5000.0)
        ->and($first->transaction_date->toDateString())->toBe('2026-01-15')
        ->and($first->period_start->toDateString())->toBe('2026-01-01')
        ->and($first->reference)->toBe('Opening balance')
        ->and($first->status)->toBe('approved')
        ->and($first->transaction_number)->toBe(sprintf('SHR-2026-%06d', $first->id))
        ->and($first->financialCycle->start_date->toDateString())->toBe('2026-01-01')
        ->and($second->number_of_shares)->toBe(2)
        ->and($second->period_start->toDateString())->toBe('2026-03-01')
        ->and($second->approved_by)->toBe($this->chair->id);
});

test('phone numbers are matched after cleaning and other groups\' members are never matched', function () {
    $other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $foreign = addMemberTo($other, User::factory()->create());
    $spaced = substr($this->plainMember->phone_number, 0, 4).' '.substr($this->plainMember->phone_number, 4);

    $response = importShares($this, "phone_number,amount,transaction_date\n\"{$spaced}\",5000,2026-01-10\n{$foreign->phone_number},5000,2026-01-10\n0000,5000,2026-01-10\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    expect(collect($response->json('data.skipped'))->pluck('reason')->unique()->all())->toBe(['No member of this group has that phone number.'])
        ->and($other->memberShareTransactions()->count())->toBe(0);
});

test('bad rows are skipped with the line number while good rows still import', function () {
    $phone = $this->plainMember->phone_number;
    $future = now()->addDay()->toDateString();
    $response = importShares($this, "phone_number,amount,transaction_date\n{$phone},0,2026-01-10\n{$phone},abc,2026-01-10\n{$phone},12000,2026-01-10\n{$phone},2500,2026-01-10\n{$phone},10000,\n{$phone},10000,15/01/2026\n{$phone},10000,{$future}\n{$phone},10000,2026-02-30\n{$phone},10000,2026-02-01\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    $skipped = collect($response->json('data.skipped'));

    expect($skipped->pluck('row')->all())->toBe([2, 3, 4, 5, 6, 7, 8, 9])
        ->and($skipped->firstWhere('row', 4)['reason'])->toContain('multiple of the share price')
        ->and($skipped->firstWhere('row', 6)['reason'])->toContain('transaction date is required')
        ->and($this->group->memberShareTransactions()->count())->toBe(1);
});

test('suspended members are skipped and the group maximum applies across rows', function () {
    $this->group->groupSetting()->update(['maximum_shares' => 5]);
    $this->friendMember->update(['membership_status' => 'suspended']);

    $response = importShares($this, "phone_number,amount,transaction_date\n{$this->plainMember->phone_number},15000,2026-01-10\n{$this->plainMember->phone_number},15000,2026-01-10\n{$this->friendMember->phone_number},5000,2026-01-10\n")
        ->assertOk()->assertJsonPath('data.imported', 1);

    $reasons = collect($response->json('data.skipped'))->pluck('reason', 'row');

    expect($reasons[3])->toContain('at most 5')->and($reasons[4])->toContain('not active');
});

test('nothing is imported until the group has a share price', function () {
    $this->group->groupSetting()->update(['share_price' => 0]);

    importShares($this, "phone_number,amount,transaction_date\n{$this->plainMember->phone_number},5000,2026-01-10\n")->assertUnprocessable()->assertJsonValidationErrors('file');

    expect($this->group->memberShareTransactions()->count())->toBe(0);
});

test('only leaders and the treasurer can import and the file must have the right headings', function () {
    $csv = "phone_number,amount,transaction_date\n{$this->plainMember->phone_number},5000,2026-01-10\n";

    importShares($this, $csv, $this->plain)->assertForbidden();
    importShares($this, $csv, $this->outsider)->assertForbidden();
    importShares($this, $csv, $this->treasurer)->assertOk();

    importShares($this, "phone,shares\n{$this->plainMember->phone_number},1\n")->assertUnprocessable()->assertJsonValidationErrors('file');
    importShares($this, "phone_number,number_of_shares,transaction_date\n{$this->plainMember->phone_number},1,2026-01-10\n")->assertUnprocessable()->assertJsonValidationErrors('file');
    importShares($this, "phone_number,amount,transaction_date\n")->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->actingAs($this->chair)->postJson(route('groups.shares.import', $this->group), [])->assertUnprocessable()->assertJsonValidationErrors('file');
});

test('imported shares count towards the totals and the member profile', function () {
    importShares($this, "phone_number,amount,transaction_date\n{$this->plainMember->phone_number},20000,2026-01-10\n")->assertOk();

    $this->actingAs($this->chair)->getJson(route('groups.shares.index', $this->group))->assertJsonPath('totals.shares', 4)->assertJsonPath('totals.amount', 20000);
    $this->actingAs($this->chair)->getJson(route('members.show', $this->plainMember))->assertJsonPath('data.summary.shares_count', 4);
});
