<?php

use App\Actions\UserManagement\ProvisionGroupDefaults;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupType;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a group created through the app is ready to use straight away', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user)->postJson(route('groups.store'), [
        'name' => 'Umoja', 'group_type_id' => GroupType::factory()->create()->id,
    ])->assertCreated()->json('data.id');

    $group = Group::query()->findOrFail($id);
    $setting = $group->groupSetting;

    expect((float) $setting->default_loan_interest_rate)->toBe(10.0)
        ->and((float) $setting->maximum_loan_multiplier)->toBe(2.0)
        ->and((float) $setting->share_price)->toBe(5000.0)
        ->and($setting->contributionFrequency->name ?? null)->toStartWith('Monthly')
        ->and($group->groupFunds()->pluck('code')->sort()->values()->all())->toBe(['GEN', 'SAV', 'SHR', 'SOC'])
        ->and($group->groupFunds()->where('code', 'SAV')->first()->fundType->code)->toBe('SAV')
        ->and($group->fineTypes()->count())->toBe(4)
        ->and($group->loanTypes()->count())->toBe(3)
        ->and($group->loanProducts()->count())->toBe(3)
        ->and($group->loanProducts()->pluck('interest_rate')->map(fn ($rate) => (float) $rate)->unique()->all())->toBe([10.0])
        ->and($group->loanProducts()->pluck('group_fund_id')->unique()->all())->toBe([$group->groupFunds()->where('code', 'SAV')->value('id')]);

    $cycle = $group->financialCycles()->sole();

    expect($cycle->status)->toBe('open')
        ->and($cycle->start_date->toDateString())->toBe(now()->startOfYear()->toDateString());
});

test('the new group can record a contribution without any setup', function () {
    $chair = User::factory()->create();
    $group = createGroupFor($chair, withDefaults: true);
    $member = $chair->groupMembers()->where('group_id', $group->id)->first() ?? $group->groupMembers()->first();

    $this->actingAs($chair)->postJson(route('contributions.store'), [
        'group_id' => $group->id,
        'group_member_id' => $member->id,
        'payment_method_id' => $group->paymentMethods()->value('id'),
        'contribution_date' => now()->toDateString(),
        'period' => now()->format('Y-m'),
        'items' => [['group_fund_id' => $group->groupFunds()->where('code', 'SAV')->value('id'), 'amount' => 20000]],
    ])->assertCreated();
});

test('provisioning twice adds nothing and keeps what leaders already changed', function () {
    $group = createGroupFor(User::factory()->create(), withDefaults: true);
    $group->groupFunds()->where('code', 'SAV')->update(['name' => 'Our Savings']);

    app(ProvisionGroupDefaults::class)->handle($group);

    expect($group->groupFunds()->count())->toBe(4)
        ->and($group->groupFunds()->where('code', 'SAV')->value('name'))->toBe('Our Savings')
        ->and($group->loanProducts()->count())->toBe(3)
        ->and($group->financialCycles()->count())->toBe(1)
        ->and($group->fineTypes()->count())->toBe(4);
});

test('the command gives an older group its missing defaults', function () {
    $group = createGroupFor(User::factory()->create());

    expect($group->groupFunds()->count())->toBe(0);

    $this->artisan('groups:provision-defaults', ['group' => $group->id])->assertSuccessful();

    expect($group->groupFunds()->count())->toBe(4)
        ->and($group->loanProducts()->count())->toBe(3)
        ->and($group->loanTypes()->count())->toBe(3)
        ->and($group->financialCycles()->count())->toBe(1);
});
