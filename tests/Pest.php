<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Group helpers
|--------------------------------------------------------------------------
*/

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Actions\UserManagement\CreateGroup;
use App\Models\LoanManagement\LoanProduct;
use App\Models\PaymentManagement\GroupFund;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\GroupMemberRole;
use App\Models\UserManagement\GroupRole;
use App\Models\UserManagement\GroupType;
use App\Models\UserManagement\User;

/**
 * Create a group exactly as a user would: the given user becomes its Chairperson.
 *
 * Funds, cycles, fine types, loan types and loan products are skipped unless $withDefaults is true,
 * so tests start from an empty group and build only what they need.
 *
 * @param  array<string, mixed>  $attributes
 */
function createGroupFor(User $chair, array $attributes = [], bool $withDefaults = false): Group
{
    return app(CreateGroup::class)->handle($chair, [
        'group_type_id' => GroupType::factory()->create()->id,
        'name' => 'Test Group',
        ...$attributes,
    ], $withDefaults);
}

/**
 * Add a user to a group as an active member, optionally holding extra system roles (e.g. "Treasurer").
 *
 * @param  array<int, string>  $roleNames
 */
function addMemberTo(Group $group, User $user, array $roleNames = []): GroupMember
{
    $member = $group->groupMembers()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'phone_number' => $user->phone_number,
        'membership_type_id' => $group->membershipTypes()->where('name', 'Ordinary Member')->value('id'),
        'member_number' => $group->nextMemberNumber(),
        'joined_at' => now()->toDateString(),
        'membership_status' => 'active',
    ]);

    foreach (['Member', ...$roleNames] as $roleName) {
        GroupMemberRole::query()->create([
            'group_member_id' => $member->id,
            'group_role_id' => GroupRole::query()->whereNull('group_id')->where('name', $roleName)->value('id'),
            'starts_at' => now()->toDateString(),
            'status' => 'active',
            'assigned_by' => $user->id,
        ]);
    }

    return $member;
}

function systemRoleId(string $name): int
{
    return (int) GroupRole::query()->whereNull('group_id')->where('name', $name)->value('id');
}

/**
 * Create an active fund for a group, defaulting to its first fund type and contribution frequency.
 *
 * @param  array<string, mixed>  $attributes
 */
function createFundFor(Group $group, array $attributes = []): GroupFund
{
    return $group->groupFunds()->create([
        'fund_type_id' => $group->fundTypes()->value('id'),
        'contribution_frequency_id' => $group->contributionFrequencies()->value('id'),
        'name' => 'Savings',
        'code' => 'SAV',
        'minimum_contribution' => 0,
        'is_mandatory' => false,
        'is_distributable' => false,
        'is_loanable' => false,
        'status' => 'active',
        'created_by' => $group->created_by,
        ...$attributes,
    ]);
}

/**
 * Create a monthly, flat-rate loan product (5% a month, 1 to 12 months, no savings cap, no guarantors) for a group.
 *
 * @param  array<string, mixed>  $attributes
 */
function createLoanProductFor(Group $group, array $attributes = []): LoanProduct
{
    return $group->loanProducts()->create([
        'group_fund_id' => createFundFor($group, ['name' => 'Loan Fund', 'code' => 'LOAN'.strtoupper(uniqid())])->id,
        'repayment_frequency_id' => $group->repaymentFrequencies()->where('interval_days', 30)->value('id'),
        'loan_interest_method_id' => $group->loanInterestMethods()->where('code', 'FLAT')->value('id'),
        'name' => 'Emergency Loan',
        'code' => 'EMG'.strtoupper(uniqid()),
        'minimum_amount' => 10000,
        'maximum_amount' => 1000000,
        'maximum_savings_multiplier' => 0,
        'interest_rate' => 5,
        'interest_period' => 'per_month',
        'minimum_duration_months' => 1,
        'maximum_duration_months' => 12,
        'grace_period_days' => 0,
        'guarantors_required' => 0,
        'status' => 'active',
        'created_by' => $group->created_by,
        ...$attributes,
    ]);
}

/**
 * Give a member approved savings, so savings-based loan limits can be tested.
 */
function giveSavings(Group $group, GroupMember $member, float $amount): void
{
    $contribution = $group->contributions()->create([
        'financial_cycle_id' => app(ResolveFinancialCycle::class)->handle($group, now(), $group->created_by)->id,
        'group_member_id' => $member->id,
        'contribution_number' => 'SAVINGS-'.uniqid(),
        'contribution_date' => now()->toDateString(),
        'total_amount' => $amount,
        'payment_method_id' => $group->paymentMethods()->value('id'),
        'status' => 'approved',
    ]);

    $contribution->contributionItems()->create([
        'group_fund_id' => $group->groupFunds()->value('id') ?? createFundFor($group)->id,
        'amount' => $amount,
    ]);
}

/**
 * Record (and approve) a contribution of $amount for a member and a month.
 */
function payFor($test, $member, string $period, float $amount, string $paidOn = '2026-04-10', bool $approve = true): int
{
    $id = $test->actingAs($test->chair)->postJson(route('contributions.store'), [
        'group_id' => $test->group->id, 'group_member_id' => $member->id, 'payment_method_id' => $test->group->paymentMethods()->value('id'),
        'contribution_date' => $paidOn, 'period' => $period, 'items' => [['group_fund_id' => $test->fund->id, 'amount' => $amount]],
    ])->assertCreated()->json('data.id');

    if ($approve) {
        $test->actingAs($test->chair)->patchJson(route('contributions.approve', $id))->assertOk();
    }

    return $id;
}

function buyShares($test, $member, string $period, int $shares, string $paidOn = '2026-04-10'): void
{
    $test->actingAs($test->chair)->postJson(route('groups.shares.store', $test->group), [
        'group_member_id' => $member->id, 'number_of_shares' => $shares, 'transaction_date' => $paidOn, 'period' => $period,
    ])->assertCreated();
}

function calendarFor($test, ?User $as = null, array $query = [])
{
    return $test->actingAs($as ?? $test->chair)->getJson(route('contribution-calendar.index', ['group_id' => $test->group->id, ...$query]));
}
