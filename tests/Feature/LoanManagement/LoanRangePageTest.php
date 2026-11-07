<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $this->plain = User::factory()->create();
    addMemberTo($this->group, $this->plain);
    $this->outsider = User::factory()->create();
    $this->other = createGroupFor(User::factory()->create(), ['name' => 'Other']);

    $this->product = createLoanProductFor($this->group, ['repayment_mode' => 'lump_sum', 'name' => 'Emergency']);
});

/**
 * @return array<string, mixed>
 */
function rangePayload($test, array $overrides = []): array
{
    return [
        'group_id' => $test->group->id, 'loan_product_id' => $test->product->id,
        'minimum_amount' => 0, 'maximum_amount' => 100000, 'duration_months' => 1, ...$overrides,
    ];
}

test('the ranges page lists the chosen group\'s ranges by product with the product options', function () {
    $this->product->loanProductRanges()->createMany([
        ['minimum_amount' => 100001, 'maximum_amount' => null, 'duration_months' => 3],
        ['minimum_amount' => 0, 'maximum_amount' => 100000, 'duration_months' => 1],
    ]);
    createLoanProductFor($this->other)->loanProductRanges()->create(['minimum_amount' => 0, 'maximum_amount' => 10, 'duration_months' => 9]);

    $this->actingAs($this->plain)->get(route('loan-ranges.index'))->assertOk()->assertSee('<loan-ranges', false);

    $response = $this->actingAs($this->plain)->getJson(route('loan-ranges.index', ['group_id' => $this->group->id]))->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('can_manage', false)->assertJsonPath('options.products.0.label', 'Emergency');

    expect(collect($response->json('data'))->pluck('duration_months')->all())->toBe([1, 3])
        ->and($response->json('data.0.product'))->toBe('Emergency')
        ->and($response->json('data.1.maximum_amount'))->toBeNull();

    $this->actingAs($this->outsider)->getJson(route('loan-ranges.index', ['group_id' => $this->group->id]))->assertForbidden();
    $this->actingAs($this->plain)->getJson(route('loan-ranges.index', ['group_id' => $this->group->id, 'q' => 'nothing']))->assertJsonCount(0, 'data');
    $this->actingAs($this->plain)->getJson(route('loan-ranges.index', ['group_id' => $this->group->id, 'product_id' => $this->product->id]))->assertJsonCount(2, 'data');
});

test('leaders and the treasurer add ranges and plain members cannot', function () {
    $this->actingAs($this->treasurer)->postJson(route('loan-ranges.store'), rangePayload($this))
        ->assertCreated()->assertJsonPath('data.duration_months', 1)->assertJsonPath('data.product', 'Emergency');

    $this->actingAs($this->plain)->postJson(route('loan-ranges.store'), rangePayload($this, ['minimum_amount' => 200000, 'maximum_amount' => null]))->assertForbidden();

    expect($this->product->loanProductRanges()->count())->toBe(1);
});

test('adding ranges makes the amount decide the term', function () {
    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['minimum_amount' => 100001, 'maximum_amount' => null, 'duration_months' => 3]))->assertCreated();

    $product = $this->product->fresh();

    expect($product->termFor(50000))->toBe(1)->and($product->termFor(250000))->toBe(3)
        ->and($product->minimum_duration_months)->toBe(1)->and($product->maximum_duration_months)->toBe(3);
});

test('ranges of one product cannot overlap but other products can reuse the same amounts', function () {
    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this))->assertCreated();

    foreach ([[50000, 150000], [100000, 200000], [0, 100000], [10, 20], [90000, null]] as [$min, $max]) {
        $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['minimum_amount' => $min, 'maximum_amount' => $max]))
            ->assertUnprocessable()->assertJsonValidationErrors('minimum_amount');
    }

    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['minimum_amount' => 100001, 'maximum_amount' => null, 'duration_months' => 3]))->assertCreated();
    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['minimum_amount' => 900000, 'maximum_amount' => null]))->assertUnprocessable();

    $second = createLoanProductFor($this->group, ['name' => 'Development']);
    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['loan_product_id' => $second->id]))->assertCreated();
});

test('amounts, terms and products are validated', function () {
    $foreign = createLoanProductFor($this->other);

    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['loan_product_id' => $foreign->id, 'minimum_amount' => -1, 'maximum_amount' => -5, 'duration_months' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors(['loan_product_id', 'minimum_amount', 'maximum_amount', 'duration_months']);

    $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this, ['minimum_amount' => 500, 'maximum_amount' => 500]))
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_amount');
});

test('a range can be changed without clashing with itself, and not across groups', function () {
    $id = $this->actingAs($this->chair)->postJson(route('loan-ranges.store'), rangePayload($this))->json('data.id');

    $this->actingAs($this->chair)->putJson(route('loan-ranges.update', $id), rangePayload($this, ['maximum_amount' => 120000, 'duration_months' => 2]))
        ->assertOk()->assertJsonPath('data.maximum_amount', 120000)->assertJsonPath('data.duration_months', 2);

    $foreign = createLoanProductFor($this->other)->loanProductRanges()->create(['minimum_amount' => 0, 'maximum_amount' => 10, 'duration_months' => 1]);

    $this->actingAs($this->chair)->putJson(route('loan-ranges.update', $foreign), rangePayload($this, ['minimum_amount' => 900000, 'maximum_amount' => null]))->assertNotFound();
    $this->actingAs($this->plain)->putJson(route('loan-ranges.update', $id), rangePayload($this))->assertForbidden();
});

test('a range can be removed and loans already given keep their term', function () {
    $this->product->loanProductRanges()->create(['minimum_amount' => 0, 'maximum_amount' => 100000, 'duration_months' => 1]);
    $member = addMemberTo($this->group, User::factory()->create());

    $applicationId = $this->actingAs($this->chair)->postJson(route('loan-applications.record'), [
        'group_id' => $this->group->id, 'group_member_id' => $member->id, 'loan_product_id' => $this->product->id,
        'requested_amount' => 50000, 'purpose' => 'History', 'application_date' => now()->subDay()->toDateString(), 'disbursement_date' => now()->toDateString(),
    ])->assertCreated()->json('data.application_id');

    $rangeId = $this->product->loanProductRanges()->value('id');

    $this->actingAs($this->plain)->deleteJson(route('loan-ranges.destroy', $rangeId))->assertForbidden();
    $this->actingAs($this->chair)->deleteJson(route('loan-ranges.destroy', $rangeId))->assertOk();

    expect($this->product->loanProductRanges()->count())->toBe(0)
        ->and($this->group->loanApplications()->findOrFail($applicationId)->duration_months)->toBe(1)
        ->and($this->group->loans()->count())->toBe(1);
});

test('the product form no longer takes ranges', function () {
    $this->actingAs($this->chair)->putJson(route('loan-products.update', $this->product), [
        ...loanProductPayload($this->group, ['code' => $this->product->code, 'status' => 'active']),
        'ranges' => [['minimum_amount' => 0, 'maximum_amount' => 10, 'duration_months' => 1]],
    ])->assertOk();

    expect($this->product->loanProductRanges()->count())->toBe(0);
});

test('the sidebar links to the loan ranges page', function () {
    $this->actingAs($this->plain)->get(route('dashboard'))->assertSee(route('loan-ranges.index'), false);
});
