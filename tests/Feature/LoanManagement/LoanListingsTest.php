<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->other = createGroupFor(User::factory()->create(), ['name' => 'Other']);
    $this->product = createLoanProductFor($this->group);
});

test('the installments page lists the chosen group\'s schedule with member visibility', function () {
    $applicationId = approvedApplication($this);
    $this->actingAs($this->chair)->postJson(route('loans.store'), ['group_id' => $this->group->id, 'loan_application_id' => $applicationId, 'disbursement_date' => now()->toDateString()])->assertCreated();

    $this->actingAs($this->plain)->get(route('loan-installments.index'))->assertOk()->assertSee('<loan-installments', false);
    $this->actingAs($this->plain)->getJson(route('loan-installments.index', ['group_id' => $this->group->id]))->assertJsonCount(3, 'data');
    $this->actingAs($this->friend)->getJson(route('loan-installments.index', ['group_id' => $this->group->id]))->assertJsonCount(0, 'data');
    $this->actingAs($this->treasurer)->getJson(route('loan-installments.index', ['group_id' => $this->group->id, 'status' => 'paid']))->assertJsonCount(0, 'data');
    $this->actingAs($this->outsider)->getJson(route('loan-installments.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('loan types use the lookup page', function () {
    $this->actingAs($this->plain)->get(route('loan-types.index'))->assertOk()->assertSee('<lookup-manager', false);

    $this->actingAs($this->chair)->postJson(route('loan-types.store'), ['group_id' => $this->group->id, 'name' => 'Emergency'])
        ->assertCreated()->assertJsonPath('data.name', 'Emergency');
    $this->actingAs($this->chair)->postJson(route('loan-types.store'), ['group_id' => $this->group->id, 'name' => 'Emergency'])->assertUnprocessable();
    $this->actingAs($this->plain)->postJson(route('loan-types.store'), ['group_id' => $this->group->id, 'name' => 'Other'])->assertForbidden();
    $this->actingAs($this->plain)->getJson(route('loan-types.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data');
});
