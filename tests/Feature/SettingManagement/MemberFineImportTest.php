<?php

use App\Models\SettingManagement\MemberFine;
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
    $this->absent = $this->group->fineTypes()->create(['name' => 'Absent', 'code' => 'ABSENT', 'default_amount' => 2000, 'calculation_type' => 'fixed', 'status' => 'active']);
    $this->custom = $this->group->fineTypes()->create(['name' => 'Custom', 'code' => 'CUSTOM', 'default_amount' => 0, 'calculation_type' => 'fixed', 'status' => 'active']);
});

function importFines($test, string $rows, ?User $as = null)
{
    return $test->actingAs($as ?? $test->chair)->postJson(route('member-fines.import'), [
        'group_id' => $test->group->id,
        'file' => UploadedFile::fake()->createWithContent('fines.csv', "phone_number,fine_type,fine_date,reason,amount,due_date\n{$rows}"),
    ]);
}

test('the template has the six columns and only leaders and the treasurer can download it', function () {
    $response = $this->actingAs($this->treasurer)->get(route('member-fines.template', ['group_id' => $this->group->id]))->assertOk()->assertDownload('member-fines-import-template.csv');
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));

    expect($lines[0])->toBe('phone_number,fine_type,fine_date,reason,amount,due_date');

    $this->actingAs($this->plain)->get(route('member-fines.template', ['group_id' => $this->group->id]))->assertForbidden();
});

test('each row becomes a pending fine, using the default amount when none is given', function () {
    importFines($this, "{$this->plainMember->phone_number},absent,15/12/2025,Missed meeting,,2026-01-15\n{$this->friendMember->phone_number},Custom,2026-03-10,Broke a rule,\"1,500\",\n")
        ->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', []);

    $first = MemberFine::query()->where('group_member_id', $this->plainMember->id)->sole();
    $second = MemberFine::query()->where('group_member_id', $this->friendMember->id)->sole();

    expect($first->status)->toBe('pending')
        ->and((float) $first->amount)->toBe(2000.0)
        ->and((float) $first->balance)->toBe(2000.0)
        ->and((float) $first->amount_paid)->toBe(0.0)
        ->and($first->fine_number)->toBe(sprintf('FN-2025-%06d', $first->id))
        ->and($first->fine_date->toDateString())->toBe('2025-12-15')
        ->and($first->due_date->toDateString())->toBe('2026-01-15')
        ->and($first->fine_type_id)->toBe($this->absent->id)
        ->and($first->issued_by)->toBe($this->chair->id)
        ->and($first->financialCycle->start_date->toDateString())->toBe('2025-01-01')
        ->and((float) $second->amount)->toBe(1500.0);
});

test('bad rows are skipped with a reason and good rows still import', function () {
    $phone = $this->plainMember->phone_number;
    $tomorrow = now()->addDay()->toDateString();

    $response = importFines($this, implode("\n", [
        '0999000111,Absent,2026-01-01,Reason,,',
        "{$phone},Nope,2026-01-01,Reason,,",
        "{$phone},Custom,2026-01-01,Reason,,",
        "{$phone},Absent,{$tomorrow},Reason,,",
        "{$phone},Absent,2026-01-01,Reason,,2025-12-01",
        "{$phone},Absent,2026-01-01,,,",
        "{$phone},Absent,2026-01-01,Fine one,,",
        "{$phone},Absent,2026-01-01,Fine one,,",
    ]))->assertOk();

    expect($response->json('data.imported'))->toBe(1)
        ->and($response->json('data.skipped'))->toHaveCount(7)
        ->and(collect($response->json('data.skipped'))->pluck('reason')->last())->toStartWith('Already imported');
    expect(MemberFine::query()->count())->toBe(1);
});

test('uploading the same file again does not duplicate fines', function () {
    $row = "{$this->plainMember->phone_number},Absent,2026-01-01,Missed meeting,,\n";

    importFines($this, $row)->assertJsonPath('data.imported', 1);
    importFines($this, $row)->assertJsonPath('data.imported', 0);

    expect(MemberFine::query()->count())->toBe(1);
});

test('a file without the required headings is rejected', function () {
    $this->actingAs($this->chair)->postJson(route('member-fines.import'), [
        'group_id' => $this->group->id,
        'file' => UploadedFile::fake()->createWithContent('fines.csv', "phone_number,amount\n0712345678,100"),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
});

test('members who are not leaders or the treasurer cannot import', function () {
    importFines($this, "{$this->friendMember->phone_number},Absent,2026-01-01,Reason,,", $this->plain)->assertForbidden();

    expect(MemberFine::query()->count())->toBe(0);
});
