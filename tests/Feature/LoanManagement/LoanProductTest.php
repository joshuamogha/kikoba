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
function loanProductPayload($group, array $overrides = []): array
{
    return [
        'group_id' => $group->id,
        'name' => 'Education Loan',
        'code' => 'edu',
        'group_fund_id' => $group->groupFunds()->value('id'),
        'repayment_frequency_id' => $group->repaymentFrequencies()->value('id'),
        'loan_interest_method_id' => $group->loanInterestMethods()->value('id'),
        'minimum_amount' => 50000,
        'maximum_amount' => 500000,
        'maximum_savings_multiplier' => 3,
        'interest_rate' => 2,
        'interest_period' => 'per_month',
        'minimum_duration_months' => 3,
        'maximum_duration_months' => 12,
        'grace_period_days' => 0,
        'guarantors_required' => 2,
        ...$overrides,
    ];
}

test('the page lists the chosen group\'s products with form options and group defaults', function () {
    createLoanProductFor($this->other);

    $this->actingAs($this->plain)->get(route('loan-products.index'))->assertOk()->assertSee('<loan-products', false);

    $this->actingAs($this->plain)->getJson(route('loan-products.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('can_manage', false)
        ->assertJsonPath('options.defaults.maximum_savings_multiplier', 3)
        ->assertJsonStructure(['options' => ['funds', 'repayment_frequencies', 'interest_methods', 'defaults']]);

    $this->actingAs($this->outsider)->getJson(route('loan-products.index', ['group_id' => $this->group->id]))->assertForbidden();
});

test('leaders and the treasurer create loan products with an upper-case code, plain members cannot', function () {
    $this->actingAs($this->treasurer)->postJson(route('loan-products.store'), loanProductPayload($this->group))
        ->assertCreated()->assertJsonPath('data.code', 'EDU')->assertJsonPath('data.status', 'active');

    $this->actingAs($this->plain)->postJson(route('loan-products.store'), loanProductPayload($this->group, ['code' => 'X']))->assertForbidden();
});

test('product limits, codes and lookups are validated', function () {
    $this->actingAs($this->chair)->postJson(route('loan-products.store'), loanProductPayload($this->group, [
        'code' => $this->product->code,
        'maximum_amount' => 10,
        'maximum_duration_months' => 1,
        'interest_period' => 'daily',
        'repayment_frequency_id' => $this->other->repaymentFrequencies()->value('id'),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['code', 'maximum_amount', 'maximum_duration_months', 'interest_period', 'repayment_frequency_id']);
});

test('a product can be deactivated but not across groups', function () {
    $foreign = createLoanProductFor($this->other);
    $payload = loanProductPayload($this->group, ['code' => $this->product->code, 'status' => 'inactive']);

    $this->actingAs($this->chair)->putJson(route('loan-products.update', $this->product), $payload)->assertOk()->assertJsonPath('data.status', 'inactive');
    $this->actingAs($this->chair)->putJson(route('loan-products.update', $foreign), [...$payload, 'code' => 'ZZ'])->assertNotFound();
});
