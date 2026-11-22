<?php

use App\Models\UserManagement\User;
use App\Support\XlsxReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->product = createLoanProductFor($this->group);

    $applicationId = approvedApplication($this);
    $this->actingAs($this->chair)->postJson(route('loans.store'), ['group_id' => $this->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString()])->assertCreated();
});

test('loans export to an Excel workbook with only the chosen columns and a totals line', function () {
    $response = $this->actingAs($this->chair)->get(route('loans.export', [
        'group_id' => $this->group->id, 'format' => 'xlsx', 'columns' => ['loan_number', 'member', 'total_payable'],
    ]))->assertOk();

    $rows = XlsxReader::rows($response->baseResponse->getFile()->getPathname());
    $rows = array_values($rows);

    expect($rows[0])->toBe(['Loan number', 'Member', 'Total payable'])
        ->and($rows[1][1])->toBe($this->plainMember->name)
        ->and($rows[2][0])->toBe('Total')
        ->and((float) $rows[2][2])->toBe((float) $rows[1][2]);
});

test('the PDF export is a print-ready page that follows the status filter', function () {
    $this->actingAs($this->chair)->get(route('loans.export', ['group_id' => $this->group->id, 'format' => 'pdf', 'columns' => ['loan_number', 'status']]))
        ->assertOk()->assertSee('Loan number')->assertSee('Active')->assertDontSee('Principal');

    $this->actingAs($this->chair)->get(route('loans.export', ['group_id' => $this->group->id, 'format' => 'pdf', 'status' => 'completed', 'columns' => ['loan_number']]))
        ->assertOk()->assertSee('Nothing found.');
});

test('export needs valid columns, and members only export their own loans', function () {
    $this->actingAs($this->chair)->getJson(route('loans.export', ['group_id' => $this->group->id]))->assertUnprocessable();
    $this->actingAs($this->chair)->getJson(route('loans.export', ['group_id' => $this->group->id, 'format' => 'xlsx', 'columns' => ['password']]))->assertUnprocessable();

    $this->actingAs($this->friend)->get(route('loans.export', ['group_id' => $this->group->id, 'format' => 'pdf', 'columns' => ['loan_number']]))
        ->assertOk()->assertSee('Nothing found.');
    $this->actingAs(User::factory()->create())->get(route('loans.export', ['group_id' => $this->group->id, 'format' => 'pdf', 'columns' => ['loan_number']]))->assertForbidden();
});
