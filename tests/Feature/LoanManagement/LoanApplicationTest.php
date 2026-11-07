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

/**
 * @return array<string, mixed>
 */
function loanApplicationPayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id,
        'group_member_id' => $test->plainMember->id,
        'loan_product_id' => $test->product->id,
        'requested_amount' => 100000,
        'duration_months' => 6,
        'purpose' => 'School fees',
        ...$overrides,
    ];
}

test('a member applies for themselves and the rate and method come from the product', function () {
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))
        ->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.interest_rate', 5);

    $application = $this->group->loanApplications()->sole();

    expect($application->application_number)->toBe(sprintf('LA-%d-%06d', now()->year, $application->id))
        ->and($application->loan_interest_method_id)->toBe($this->product->loan_interest_method_id)
        ->and($application->financialCycle->status)->toBe('open');
});

test('a member cannot apply for someone else but leaders can', function () {
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this, ['group_member_id' => $this->friendMember->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('group_member_id');

    $this->actingAs($this->treasurer)->postJson(route('loan-applications.store'), loanApplicationPayload($this, ['group_member_id' => $this->friendMember->id]))->assertCreated();
    $this->actingAs($this->outsider)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->assertForbidden();
});

test('amount, duration and savings limits are enforced', function () {
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this, ['requested_amount' => 5, 'duration_months' => 24]))
        ->assertUnprocessable()->assertJsonValidationErrors(['requested_amount', 'duration_months']);

    $this->product->update(['maximum_savings_multiplier' => 3]);
    giveSavings($this->group, $this->plainMember, 20000);

    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this, ['requested_amount' => 100000]))
        ->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this, ['requested_amount' => 60000]))->assertCreated();
});

test('a member with a loan in progress cannot apply again unless the group allows it', function () {
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->assertCreated();
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))
        ->assertUnprocessable()->assertJsonValidationErrors('group_member_id');

    $this->group->groupSetting()->update(['allow_multiple_loans' => true]);
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->assertCreated();
});

test('approval needs the product\'s accepted guarantors and an amount within the request', function () {
    $this->product->update(['guarantors_required' => 1]);
    $id = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => 100000])->assertUnprocessable();

    $guarantor = $this->actingAs($this->chair)->postJson(route('loan-guarantors.store'), [
        'group_id' => $this->group->id, 'loan_application_id' => $id, 'group_member_id' => $this->friendMember->id, 'guaranteed_amount' => 50000,
    ])->json('data.id');
    $this->actingAs($this->friend)->patchJson(route('loan-guarantors.respond', $guarantor), ['decision' => 'accepted'])->assertOk();

    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => 200000])->assertUnprocessable();
    $this->actingAs($this->plain)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => 90000])->assertForbidden();
    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => 90000])
        ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.approved_amount', 90000);
    $this->actingAs($this->chair)->patchJson(route('loan-applications.approve', $id), ['approved_amount' => 90000])->assertUnprocessable();
});

test('rejecting needs a reason and an application can be cancelled by its owner', function () {
    $first = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->json('data.id');

    $this->actingAs($this->chair)->patchJson(route('loan-applications.reject', $first), [])->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
    $this->actingAs($this->chair)->patchJson(route('loan-applications.reject', $first), ['rejection_reason' => 'Too many loans'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    $second = $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->json('data.id');
    $this->actingAs($this->friend)->patchJson(route('loan-applications.cancel', $second))->assertForbidden();
    $this->actingAs($this->plain)->patchJson(route('loan-applications.cancel', $second))->assertOk()->assertJsonPath('data.status', 'cancelled');
});

test('members only see their own applications and managers see all', function () {
    $this->actingAs($this->plain)->postJson(route('loan-applications.store'), loanApplicationPayload($this))->assertCreated();
    $this->actingAs($this->friend)->postJson(route('loan-applications.store'), loanApplicationPayload($this, ['group_member_id' => $this->friendMember->id]))->assertCreated();

    $this->actingAs($this->plain)->get(route('loan-applications.index'))->assertOk()->assertSee('<loan-applications', false);
    $this->actingAs($this->plain)->getJson(route('loan-applications.index', ['group_id' => $this->group->id]))
        ->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false)->assertJsonPath('options.own_member_id', $this->plainMember->id);
    $this->actingAs($this->treasurer)->getJson(route('loan-applications.index', ['group_id' => $this->group->id]))->assertJsonCount(2, 'data');
    $this->actingAs($this->outsider)->getJson(route('loan-applications.index', ['group_id' => $this->group->id]))->assertForbidden();
});
