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

beforeEach(function () {
    $this->applicationId = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'loan_product_id' => $this->product->id,
        'requested_amount' => 100000, 'duration_months' => 6, 'purpose' => 'School fees',
    ])->json('data.id');
});

/**
 * @return array<string, mixed>
 */
function guarantorPayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'loan_application_id' => $test->applicationId,
        'group_member_id' => $test->friendMember->id,
        'guaranteed_amount' => 50000,
        ...$overrides,
    ];
}

test('leaders and the applicant can add guarantors, other members cannot', function () {
    $this->actingAs($this->plain)->postJson(route('loan-guarantors.store'), guarantorPayload($this))->assertCreated()->assertJsonPath('data.status', 'pending');
    $this->actingAs($this->treasurer)->postJson(route('loan-guarantors.store'), guarantorPayload($this, ['group_member_id' => $this->treasurerMember->id]))->assertCreated();
    $this->actingAs($this->friend)->postJson(route('loan-guarantors.store'), guarantorPayload($this, ['group_member_id' => $this->treasurerMember->id]))->assertForbidden();
});

test('a member cannot guarantee their own loan or guarantee twice', function () {
    $this->actingAs($this->plain)->postJson(route('loan-guarantors.store'), guarantorPayload($this, ['group_member_id' => $this->plainMember->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('group_member_id');

    $this->actingAs($this->plain)->postJson(route('loan-guarantors.store'), guarantorPayload($this))->assertCreated();
    $this->actingAs($this->plain)->postJson(route('loan-guarantors.store'), guarantorPayload($this))->assertUnprocessable()->assertJsonValidationErrors('group_member_id');
});

test('only the guarantor or a leader can answer, and only once', function () {
    $id = $this->actingAs($this->plain)->postJson(route('loan-guarantors.store'), guarantorPayload($this))->json('data.id');

    $this->actingAs($this->plain)->patchJson(route('loan-guarantors.respond', $id), ['decision' => 'accepted'])->assertForbidden();
    $this->actingAs($this->friend)->patchJson(route('loan-guarantors.respond', $id), ['decision' => 'maybe'])->assertUnprocessable();
    $this->actingAs($this->friend)->patchJson(route('loan-guarantors.respond', $id), ['decision' => 'declined', 'remarks' => 'Cannot'])
        ->assertOk()->assertJsonPath('data.status', 'declined');
    $this->actingAs($this->friend)->patchJson(route('loan-guarantors.respond', $id), ['decision' => 'accepted'])->assertUnprocessable();
});

test('members see the guarantees they give or receive and leaders see all', function () {
    $this->actingAs($this->plain)->postJson(route('loan-guarantors.store'), guarantorPayload($this))->assertCreated();

    $this->actingAs($this->friend)->get(route('loan-guarantors.index'))->assertOk()->assertSee('<loan-guarantors', false);
    $this->actingAs($this->friend)->getJson(route('loan-guarantors.index', ['group_id' => $this->group->id]))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_mine', true);
    $this->actingAs($this->plain)->getJson(route('loan-guarantors.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data');
    $this->actingAs($this->treasurer)->getJson(route('loan-guarantors.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data');
    $this->actingAs($this->outsider)->getJson(route('loan-guarantors.index', ['group_id' => $this->group->id]))->assertForbidden();
});
