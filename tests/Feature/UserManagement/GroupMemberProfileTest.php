<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->chairMember = $this->group->groupMembers()->where('user_id', $this->chair->id)->firstOrFail();
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();
});

beforeEach(function () {
    $this->fund = createFundFor($this->group);
    $this->product = createLoanProductFor($this->group);
});

function seedMemberHistory($test): void
{
    // An approved contribution, which also logs a payment transaction.
    $id = $test->actingAs($test->chair)->postJson(route('contributions.store'), [
        'group_id' => $test->group->id, 'group_member_id' => $test->plainMember->id, 'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'contribution_date' => now()->toDateString(), 'items' => [['group_fund_id' => $test->fund->id, 'amount' => 30000]],
    ])->json('data.id');
    $test->actingAs($test->chair)->patchJson(route('contributions.approve', $id))->assertOk();

    // Shares.
    $test->group->groupSetting()->update(['share_price' => 5000]);
    $test->actingAs($test->chair)->postJson(route('groups.shares.store', $test->group), [
        'group_member_id' => $test->plainMember->id, 'number_of_shares' => 4, 'transaction_date' => now()->toDateString(),
    ])->assertCreated();

    // A loan with an approved repayment.
    $application = $test->actingAs($test->plain)->postJson(route('loan-applications.store'), [
        'group_id' => $test->group->id, 'group_member_id' => $test->plainMember->id, 'loan_product_id' => $test->product->id,
        'requested_amount' => 100000, 'duration_months' => 3, 'purpose' => 'Business',
    ])->json('data.id');
    $test->actingAs($test->chair)->patchJson(route('loan-applications.approve', $application), ['approved_amount' => 100000])->assertOk();
    $loan = $test->actingAs($test->chair)->postJson(route('loans.store'), ['group_id' => $test->group->id, 'loan_application_id' => $application, 'disbursement_date' => now()->toDateString()])->json('data.id');
    $repayment = $test->actingAs($test->chair)->postJson(route('loan-repayments.store'), [
        'group_id' => $test->group->id, 'loan_id' => $loan, 'amount' => 20000, 'payment_date' => now()->toDateString(), 'payment_method_id' => $test->group->paymentMethods()->value('id'),
    ])->json('data.id');
    $test->actingAs($test->chair)->patchJson(route('loan-repayments.approve', $repayment))->assertOk();

    // A fine.
    $test->group->fineTypes()->create(['name' => 'Absent', 'code' => 'ABSENT', 'default_amount' => 2000, 'calculation_type' => 'fixed', 'status' => 'active']);
    $test->actingAs($test->chair)->postJson(route('member-fines.store'), [
        'group_id' => $test->group->id, 'group_member_id' => $test->plainMember->id, 'fine_type_id' => $test->group->fineTypes()->value('id'),
        'reason' => 'Missed meeting', 'fine_date' => now()->toDateString(), 'amount' => 2000,
    ])->assertCreated();
}

test('leaders see a member\'s profile page and summary figures', function () {
    seedMemberHistory($this);

    $this->actingAs($this->chair)->get(route('members.show', $this->plainMember))->assertOk()->assertSee('<member-profile', false);

    $this->actingAs($this->chair)->getJson(route('members.show', $this->plainMember))
        ->assertOk()
        ->assertJsonPath('data.name', $this->plain->name)
        ->assertJsonPath('data.group.id', $this->group->id)
        ->assertJsonPath('data.summary.contributions_total', 30000)
        ->assertJsonPath('data.summary.shares_count', 4)
        ->assertJsonPath('data.summary.shares_amount', 20000)
        ->assertJsonPath('data.summary.loans_count', 1)
        ->assertJsonPath('data.summary.loans_outstanding', 95000)
        ->assertJsonPath('data.summary.fines_balance', 2000);
});

test('every record tab lists the member\'s own rows', function () {
    seedMemberHistory($this);

    $counts = ['contributions' => 1, 'shares' => 1, 'loans' => 1, 'repayments' => 1, 'fines' => 1, 'transactions' => 2, 'guarantees' => 0];

    foreach ($counts as $type => $count) {
        $this->actingAs($this->chair)->getJson(route('members.records', [$this->plainMember, $type]))
            ->assertOk()->assertJsonCount($count, 'data');
    }

    $this->actingAs($this->chair)->getJson(route('members.records', [$this->friendMember, 'contributions']))->assertJsonCount(0, 'data');
});

test('a member can open their own profile but not another member\'s', function () {
    $this->actingAs($this->plain)->getJson(route('members.show', $this->plainMember))->assertOk();
    $this->actingAs($this->plain)->getJson(route('members.records', [$this->plainMember, 'loans']))->assertOk();
    $this->actingAs($this->plain)->getJson(route('members.show', $this->friendMember))->assertForbidden();
    $this->actingAs($this->plain)->getJson(route('members.records', [$this->friendMember, 'loans']))->assertForbidden();
    $this->actingAs($this->outsider)->getJson(route('members.show', $this->plainMember))->assertForbidden();
});

test('an unknown record tab is not found', function () {
    $this->actingAs($this->chair)->getJson('/members/'.$this->plainMember->id.'/records/secrets')->assertNotFound();
});

test('the members list links only to profiles the viewer may open', function () {
    $response = $this->actingAs($this->plain)->getJson(route('members.index', ['group_id' => $this->group->id]))->assertOk();
    $self = collect($response->json('data'))->firstWhere('id', $this->plainMember->id);
    $other = collect($response->json('data'))->firstWhere('id', $this->friendMember->id);

    expect($self['is_self'])->toBeTrue()->and($other['is_self'])->toBeFalse();
});
