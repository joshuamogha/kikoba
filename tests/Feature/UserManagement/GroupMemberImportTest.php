<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    addMemberTo($this->group, $this->plain);
    $this->outsider = User::factory()->create();
});

function memberCsv(string $contents, string $name = 'members.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function importMembers($test, string $contents, ?User $as = null)
{
    return $test->actingAs($as ?? $test->chair)->postJson(route('members.import'), [
        'group_id' => $test->group->id,
        'file' => memberCsv($contents),
    ]);
}

test('the template is a csv with the two column headings and sample rows', function () {
    $response = $this->actingAs($this->plain)->get(route('members.template'))->assertOk()->assertDownload('members-import-template.csv');
    $lines = array_filter(explode("\n", str_replace("\r", '', $response->streamedContent())));

    expect(array_values($lines)[0])->toBe('name,phone_number')->and($lines)->toHaveCount(3);
});

test('the members page lists the chosen group\'s members and shows the import option only to leaders', function () {
    $this->actingAs($this->plain)->get(route('members.index'))->assertOk()->assertSee('<members', false);

    $this->actingAs($this->plain)->getJson(route('members.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('can_manage', false);
    $this->actingAs($this->chair)->getJson(route('members.index', ['group_id' => $this->group->id, 'q' => $this->plain->name]))
        ->assertJsonCount(1, 'data')->assertJsonPath('can_manage', true);
    $this->actingAs($this->outsider)->getJson(route('members.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('leaders get the membership type options and add a member from the same page', function () {
    $this->actingAs($this->plain)->getJson(route('members.index', ['group_id' => $this->group->id]))->assertJsonPath('options', null);

    $typeId = $this->actingAs($this->chair)->getJson(route('members.index', ['group_id' => $this->group->id]))
        ->assertJsonPath('options.membership_types.0.label', 'Founder Member')->json('options.membership_types.1.value');

    $this->actingAs($this->chair)->postJson(route('groups.members.store', $this->group), [
        'login' => '0712 000 111', 'name' => 'Added By Hand', 'membership_type_id' => $typeId, 'joined_at' => now()->toDateString(),
    ])->assertCreated()->assertJsonPath('data.name', 'Added By Hand');

    $this->actingAs($this->chair)->getJson(route('members.index', ['group_id' => $this->group->id, 'q' => 'Added By Hand']))->assertJsonCount(1, 'data');
});

test('leaders import members by name and phone number', function () {
    importMembers($this, "name,phone_number\nAsha Juma,0712 345 678\nJohn Mushi,+255754123456\n")
        ->assertOk()->assertJsonPath('data.imported', 2)->assertJsonPath('data.skipped', []);

    $asha = $this->group->groupMembers()->where('name', 'Asha Juma')->firstOrFail();

    expect($asha->phone_number)->toBe('0712345678')
        ->and($asha->membership_status)->toBe('active')
        ->and($asha->user_id)->toBeNull()
        ->and($asha->membershipType->name)->toBe('Ordinary Member')
        ->and($asha->groupMemberRoles()->active()->count())->toBe(1)
        ->and($this->group->groupMembers()->count())->toBe(4);
});

test('a phone number of a registered user links to their account', function () {
    $user = User::factory()->create(['phone_number' => '255700111222']);

    importMembers($this, "name,phone_number\nSome Name,255700111222\n")->assertOk()->assertJsonPath('data.imported', 1);

    expect($this->group->groupMembers()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('bad rows are skipped and reported with the line number while good rows still import', function () {
    $response = importMembers($this, "name,phone_number\nGood Person,0711000111\n,0711000222\nBad Phone,12\nDuplicate,0711000111\n\nGood Two,0711000333\n")
        ->assertOk()->assertJsonPath('data.imported', 2);

    $skipped = collect($response->json('data.skipped'))->keyBy('row');

    expect($skipped->keys()->all())->toBe([3, 4, 5])
        ->and($skipped[3]['reason'])->toContain('name')
        ->and($skipped[4]['reason'])->toContain('phone')
        ->and($skipped[5]['reason'])->toContain('Already');
});

test('people already in the group are skipped but an exited member comes back', function () {
    $exited = addMemberTo($this->group, User::factory()->create());
    $exited->update(['membership_status' => 'exited', 'phone_number' => '0788000999']);

    importMembers($this, "name,phone_number\nBack Again,0788000999\n{$this->plain->name},{$this->plain->phone_number}\n")
        ->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped.0.row', 3);

    expect($exited->fresh()->membership_status)->toBe('active')->and($exited->fresh()->name)->toBe('Back Again');
});

test('the group\'s maximum number of members is respected', function () {
    $this->group->update(['maximum_members' => 3]);

    importMembers($this, "name,phone_number\nOne,0711000001\nTwo,0711000002\n")
        ->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped.0.reason', 'The group has reached its maximum number of members.');
});

test('only leaders can import', function () {
    importMembers($this, "name,phone_number\nA B,0711000001\n", $this->plain)->assertForbidden();
    importMembers($this, "name,phone_number\nA B,0711000001\n", $this->outsider)->assertForbidden();
});

test('the file must be a csv with the right headings and at least one member', function () {
    importMembers($this, "full name,phone\nA B,0711000001\n")->assertUnprocessable()->assertJsonValidationErrors('file');
    importMembers($this, "name,phone_number\n")->assertUnprocessable()->assertJsonValidationErrors('file');

    $this->actingAs($this->chair)->postJson(route('members.import'), ['group_id' => $this->group->id])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->actingAs($this->chair)->postJson(route('members.import'), ['group_id' => $this->group->id, 'file' => UploadedFile::fake()->create('members.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors('file');

    expect($this->group->groupMembers()->count())->toBe(2);
});
