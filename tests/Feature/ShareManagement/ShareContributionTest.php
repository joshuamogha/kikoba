<?php

use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->member = addMemberTo($this->group, User::factory()->create());
    $this->group->groupSetting()->update(['share_price' => 5000, 'maximum_shares' => 5, 'minimum_shares' => 0]);
});

function addSharesFund($test)
{
    return createFundFor($test->group, [
        'fund_type_id' => $test->group->fundTypes()->where('code', 'SHR')->value('id'),
        'name' => 'Hisa (Shares)',
        'code' => 'SHR',
    ]);
}

function importShareRows($test, string $rows)
{
    return $test->actingAs($test->chair)->postJson(route('groups.shares.import', $test->group), [
        'file' => UploadedFile::fake()->createWithContent('shares.csv', "phone_number,amount,transaction_date,reference\n{$rows}"),
    ])->assertOk();
}

test('an imported share purchase is also an approved contribution to the Shares fund, and the share record is kept', function () {
    $fund = addSharesFund($this);

    importShareRows($this, "{$this->member->phone_number},20000,15/12/2025 08:32:47,Slip 9\n");

    $purchase = MemberShareTransaction::query()->sole();
    $contribution = $purchase->contribution;

    expect($purchase->number_of_shares)->toBe(4)
        ->and($contribution)->not->toBeNull()
        ->and($contribution->status)->toBe('approved')
        ->and((float) $contribution->total_amount)->toBe(20000.0)
        ->and($contribution->group_member_id)->toBe($this->member->id)
        ->and($contribution->contribution_date->toDateString())->toBe('2025-12-15')
        ->and($contribution->created_at->format('Y-m-d H:i:s'))->toBe('2025-12-15 08:32:47')
        ->and($contribution->external_reference)->toBe('Slip 9')
        ->and($contribution->financial_cycle_id)->toBe($purchase->financial_cycle_id)
        ->and($contribution->contribution_number)->toBe(sprintf('CON-2025-%06d', $contribution->id))
        ->and($contribution->contributionItems()->sole()->group_fund_id)->toBe($fund->id)
        ->and($contribution->contributionItems()->sole()->period_start->toDateString())->toBe('2025-12-01')
        ->and(PaymentTransaction::query()->where('reference_id', $contribution->id)->where('transaction_type', 'contribution')->count())->toBe(1);

    $this->actingAs($this->chair)->getJson(route('contributions.index', ['group_id' => $this->group->id]))
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.funds.0.name', 'Hisa (Shares)');
});

test('shares carried to the next month get one contribution per month', function () {
    addSharesFund($this);

    importShareRows($this, "{$this->member->phone_number},40000,2026-01-20,\n");

    expect(MemberShareTransaction::query()->count())->toBe(2)
        ->and(MemberShareTransaction::query()->whereNull('contribution_id')->count())->toBe(0)
        ->and($this->group->contributions()->count())->toBe(2)
        ->and((float) $this->group->contributions()->sum('total_amount'))->toBe(40000.0);
});

test('the add-shares form records the contribution too', function () {
    addSharesFund($this);

    buyShares($this, $this->member, '2026-02', 3, '2026-02-10');

    expect($this->group->contributions()->sole()->total_amount)->toEqual(15000)
        ->and(MemberShareTransaction::query()->sole()->contribution_id)->not->toBeNull();
});

test('share money shows month by month on both the shares and the contributions calendar', function () {
    addSharesFund($this);
    buyShares($this, $this->member, '2026-02', 3, '2026-02-10');
    $cycleId = MemberShareTransaction::query()->value('financial_cycle_id');

    foreach (['shares', 'contributions'] as $type) {
        calendarFor($this, query: ['type' => $type, 'financial_cycle_id' => $cycleId])
            ->assertOk()->assertJsonPath('data.grand_total', 15000)->assertJsonPath('data.totals.2026-02', 15000);
    }
});

test('a group without a Shares fund gets one the first time shares are recorded', function () {
    expect($this->group->groupFunds()->count())->toBe(0);

    importShareRows($this, "{$this->member->phone_number},5000,2026-01-10,\n");

    expect($this->group->groupFunds()->sole()->code)->toBe('SHR')
        ->and($this->group->contributions()->sole()->contributionItems()->sole()->groupFund->code)->toBe('SHR');
});

test('without the Shares fund type the share records are still saved', function () {
    $this->group->fundTypes()->where('code', 'SHR')->delete();

    importShareRows($this, "{$this->member->phone_number},5000,2026-01-10,\n");

    expect(MemberShareTransaction::query()->count())->toBe(1)
        ->and($this->group->contributions()->count())->toBe(0);
});

test('the command adds contributions for share purchases that have none, once', function () {
    $this->group->fundTypes()->where('code', 'SHR')->update(['code' => 'XXX']);
    importShareRows($this, "{$this->member->phone_number},10000,2026-01-10,old\n");
    $this->group->fundTypes()->where('code', 'XXX')->update(['code' => 'SHR']);

    expect($this->group->contributions()->count())->toBe(0);

    $this->artisan('shares:link-contributions')->assertSuccessful();
    $this->artisan('shares:link-contributions')->assertSuccessful();

    $purchase = MemberShareTransaction::query()->sole();

    expect($this->group->contributions()->count())->toBe(1)
        ->and($purchase->contribution_id)->not->toBeNull()
        ->and($purchase->contribution->created_at->toDateString())->toBe('2026-01-10');
});
