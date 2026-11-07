<?php

use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('the seeder creates Tanzanian groups with members, funds and cycles', function () {
    expect(DB::table('groups')->pluck('currency')->unique()->all())->toBe(['TZS'])
        ->and(DB::table('groups')->count())->toBe(2)
        ->and(DB::table('group_members')->count())->toBe(22)
        ->and(DB::table('financial_cycles')->where('status', 'active')->count())->toBe(2)
        ->and(DB::table('payment_methods')->where('code', 'MPESA')->count())->toBe(2);
});

test('seeded users can sign in with their phone number', function () {
    $this->post(route('login'), ['login' => '0754000001', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('seeded money columns reconcile', function () {
    $contributionsOutOfBalance = DB::table('contributions as c')
        ->whereRaw('c.total_amount != (select sum(amount) from contribution_items where contribution_id = c.id)')
        ->count();

    $loansOutOfBalance = DB::table('loans')
        ->whereRaw('total_payable - amount_paid != outstanding_balance')
        ->count();

    $shareOutsOutOfBalance = DB::table('share_outs as s')
        ->whereRaw('s.total_distributable != (select sum(net_payable) from member_share_outs where share_out_id = s.id)')
        ->count();

    expect($contributionsOutOfBalance)->toBe(0)
        ->and($loansOutOfBalance)->toBe(0)
        ->and($shareOutsOutOfBalance)->toBe(0);
});

test('every transaction type has seeded data', function () {
    $tables = [
        'contributions', 'contribution_items', 'member_share_transactions', 'loan_applications', 'loan_guarantors',
        'loans', 'loan_installments', 'loan_repayments', 'member_fines', 'fine_payments', 'group_incomes',
        'group_expenses', 'payment_transactions', 'share_outs', 'member_share_outs', 'share_out_payments',
    ];

    foreach ($tables as $table) {
        expect(DB::table($table)->count())->toBeGreaterThan(0, $table);
    }
});

test('only the seeded administrator is a platform admin', function () {
    expect(DB::table('users')->where('is_platform_admin', true)->pluck('email')->all())->toBe(['admin@kibubu.co.tz']);
});

test('the seeded chairperson can manage the Kinondoni group', function () {
    $neema = User::query()->where('name', 'Neema Mushi')->firstOrFail();
    $kinondoni = Group::query()->where('name', 'like', 'Umoja wa Wanawake%')->firstOrFail();

    expect($neema->leadsGroup($kinondoni))->toBeTrue();

    $this->actingAs($neema)->getJson(route('groups.members.index', $kinondoni))
        ->assertOk()
        ->assertJsonPath('meta.total', 12);
});

test('a seeded group member can open their group but not another one', function () {
    $baraka = User::query()->where('name', 'Baraka Mollel')->firstOrFail();
    $kinondoni = Group::query()->where('name', 'like', 'Umoja wa Wanawake%')->firstOrFail();
    $arusha = Group::query()->where('name', 'like', 'Tumaini%')->firstOrFail();

    $this->actingAs($baraka)->get(route('groups.show', $arusha))->assertOk();
    $this->actingAs($baraka)->get(route('groups.show', $kinondoni))->assertForbidden();
});
