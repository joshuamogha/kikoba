<?php

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use App\Models\ShareManagement\ShareOutPayment;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * One share out with a row, a payment and a share purchase for each given member.
 *
 * @param  array<int, GroupMember>  $members
 */
function shareOutFor(Group $group, array $members, string $status = 'approved'): ShareOut
{
    $cycle = FinancialCycle::factory()->create(['group_id' => $group->id]);
    $shareOut = ShareOut::factory()->create(['group_id' => $group->id, 'financial_cycle_id' => $cycle->id, 'status' => $status]);

    foreach ($members as $member) {
        $row = MemberShareOut::factory()->create(['share_out_id' => $shareOut->id, 'group_member_id' => $member->id]);

        ShareOutPayment::query()->create([
            'member_share_out_id' => $row->id,
            'payment_number' => "P-{$shareOut->id}-{$member->id}",
            'payment_date' => '2025-12-27',
            'amount' => 1000,
            'payment_method_id' => $group->paymentMethods()->value('id'),
            'status' => 'paid',
        ]);

        DB::table('member_share_transactions')->insert([
            'group_id' => $group->id,
            'financial_cycle_id' => $cycle->id,
            'group_member_id' => $member->id,
            'transaction_number' => "T-{$shareOut->id}-{$member->id}",
            'transaction_type' => 'purchase',
            'number_of_shares' => 2,
            'price_per_share' => 10000,
            'total_amount' => 20000,
            'transaction_date' => '2025-03-01',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $shareOut;
}

beforeEach(function () {
    $this->chair = User::factory()->create(['name' => 'Neema Mushi']);
    $this->group = createGroupFor($this->chair);
    $this->treasurer = User::factory()->create();
    $this->plain = User::factory()->create(['name' => 'Asha Juma']);
    $this->stranger = User::factory()->create();
    $this->admin = User::factory()->create(['is_platform_admin' => true]);

    $chairMember = $this->group->groupMembers()->firstOrFail();
    addMemberTo($this->group, $this->treasurer, ['Treasurer']);
    $plainMember = addMemberTo($this->group, $this->plain);

    $this->otherChair = User::factory()->create();
    $this->otherGroup = createGroupFor($this->otherChair, ['name' => 'Other Group']);

    shareOutFor($this->group, [$chairMember, $plainMember]);
    shareOutFor($this->otherGroup, [$this->otherGroup->groupMembers()->firstOrFail()]);
});

$routes = ['share-outs.index', 'member-share-outs.index', 'share-out-payments.index', 'member-share-transactions.index'];

test('guests are sent to sign in', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with($routes);

test('each page renders for a signed-in user', function (string $route) {
    $this->actingAs($this->plain)->get(route($route))->assertOk();
})->with($routes);

test('share outs are listed for every group the viewer belongs to', function (string $who, int $expected) {
    $this->actingAs($this->{$who})->getJson(route('share-outs.index'))->assertOk()->assertJsonCount($expected, 'data');
})->with([
    'member' => ['plain', 1],
    'treasurer' => ['treasurer', 1],
    'other group chair' => ['otherChair', 1],
    'stranger' => ['stranger', 0],
    'platform admin' => ['admin', 2],
]);

test('member rows, payments and share purchases follow the same visibility', function (string $route, string $who, int $expected) {
    $this->actingAs($this->{$who})->getJson(route($route))->assertOk()->assertJsonCount($expected, 'data');
})->with(function () use ($routes) {
    foreach (array_slice($routes, 1) as $route) {
        foreach ([['plain', 1], ['treasurer', 2], ['chair', 2], ['otherChair', 1], ['stranger', 0], ['admin', 3]] as [$who, $count]) {
            yield "$route as $who" => [$route, $who, $count];
        }
    }
});

test('a member only sees their own name on member rows', function () {
    $this->actingAs($this->plain)->getJson(route('member-share-outs.index'))
        ->assertJsonPath('data.0.member.name', 'Asha Juma')
        ->assertJsonPath('data.0.member.is_self', true)
        ->assertJsonPath('data.0.share_out.group.name', $this->group->name);
});

test('the group filter narrows the list and ignores groups the viewer cannot see', function () {
    $this->actingAs($this->admin)->getJson(route('member-share-outs.index', ['group_id' => $this->otherGroup->id]))->assertJsonCount(1, 'data');
    $this->actingAs($this->plain)->getJson(route('member-share-outs.index', ['group_id' => $this->otherGroup->id]))->assertJsonCount(0, 'data');
    $this->actingAs($this->plain)->getJson(route('share-out-payments.index', ['group_id' => $this->otherGroup->id]))->assertJsonCount(0, 'data');
    $this->actingAs($this->admin)->getJson(route('share-outs.index', ['group_id' => $this->group->id]))->assertJsonCount(1, 'data');
});

test('search and status filters work', function () {
    $this->actingAs($this->chair)->getJson(route('member-share-outs.index', ['q' => 'Asha']))->assertJsonCount(1, 'data');
    $this->actingAs($this->chair)->getJson(route('member-share-outs.index', ['status' => 'paid']))->assertJsonCount(0, 'data');
    $this->actingAs($this->chair)->getJson(route('share-out-payments.index', ['q' => 'Asha']))->assertJsonCount(1, 'data');
    $this->actingAs($this->chair)->getJson(route('member-share-transactions.index', ['q' => 'Asha']))->assertJsonCount(1, 'data');
    $this->actingAs($this->chair)->getJson(route('share-outs.index', ['status' => 'draft']))->assertJsonCount(0, 'data');
    $this->actingAs($this->chair)->getJson(route('share-outs.index', ['status' => 'approved']))->assertJsonCount(1, 'data');
});

test('the group filter options only contain the viewer\'s groups', function () {
    $this->actingAs($this->plain)->get(route('share-outs.index'))
        ->assertSee($this->group->name)
        ->assertDontSee('Other Group');
    $this->actingAs($this->admin)->get(route('share-outs.index'))->assertSee('Other Group');
});

test('member share transactions report totals for the visible rows', function () {
    $response = $this->actingAs($this->chair)->getJson(route('member-share-transactions.index'))->assertOk();

    expect($response->json('totals.shares'))->toBe((int) collect($response->json('data'))->sum('shares'))
        ->and((float) $response->json('totals.amount'))->toBe((float) collect($response->json('data'))->sum('total_amount'));
});
