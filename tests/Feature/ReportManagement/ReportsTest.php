<?php

use App\Actions\LoanManagement\RecordExistingLoan;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chair = User::factory()->create();
    $this->group = createGroupFor($this->chair);
    $this->plain = User::factory()->create();
    $this->plainMember = addMemberTo($this->group, $this->plain);
    $this->friend = User::factory()->create();
    $this->friendMember = addMemberTo($this->group, $this->friend);
    $this->outsider = User::factory()->create();

    $this->cycle = $this->group->financialCycles()->create([
        'contribution_frequency_id' => $this->group->contributionFrequencies()->value('id'),
        'name' => 'FY 2025/26', 'cycle_number' => 1, 'start_date' => '2025-12-01', 'end_date' => '2026-11-30', 'status' => 'open',
    ]);

    $this->fund = createFundFor($this->group, ['name' => 'Savings', 'code' => 'SAV']);
    $this->social = createFundFor($this->group, ['name' => 'Jamii', 'code' => 'SOC']);
    createFundFor($this->group, ['fund_type_id' => $this->group->fundTypes()->where('code', 'SHR')->value('id'), 'name' => 'Hisa', 'code' => 'SHR']);
    $this->group->groupSetting()->update(['share_price' => 5000, 'maximum_shares' => null]);

    buyShares($this, $this->plainMember, '2025-12', 2, '2025-12-10');
    buyShares($this, $this->plainMember, '2026-11', 3, '2026-11-05');
    buyShares($this, $this->friendMember, '2026-01', 1, '2026-01-12');
    payFor($this, $this->plainMember, '2026-01', 20000, '2026-01-15');
    $this->actingAs($this->chair)->postJson(route('contributions.store'), [
        'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'contribution_date' => '2026-02-10', 'period' => '2026-02', 'items' => [['group_fund_id' => $this->social->id, 'amount' => 5000]],
    ])->assertCreated();
    $contributionId = $this->group->contributions()->whereDate('contribution_date', '2026-02-10')->value('id');
    $this->actingAs($this->chair)->patchJson(route('contributions.approve', $contributionId))->assertOk();

    $this->product = createLoanProductFor($this->group);
    [, $this->loan] = app(RecordExistingLoan::class)->handle(
        $this->group, $this->product, $this->chair,
        ['group_member_id' => $this->plainMember->id, 'requested_amount' => 100000, 'duration_months' => 3, 'purpose' => 'Test'],
        Carbon::parse('2026-03-01'), Carbon::parse('2026-03-01'),
    );
    $repayment = $this->actingAs($this->chair)->postJson(route('loan-repayments.store'), [
        'group_id' => $this->group->id, 'loan_id' => $this->loan->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
        'payment_date' => '2026-04-01', 'amount' => 40000,
    ])->assertCreated()->json('data.id');
    $this->actingAs($this->chair)->patchJson(route('loan-repayments.approve', $repayment))->assertOk();
});

function reportUrl(string $name, $test, array $query = []): string
{
    return route("reports.{$name}", ['group_id' => $test->group->id, 'financial_cycle_id' => $test->cycle->id, ...$query]);
}

test('the member statement shows shares by month, contributions by fund and the loan history with balance', function () {
    $response = $this->actingAs($this->chair)->getJson(reportUrl('member-statement', $this, ['group_member_id' => $this->plainMember->id]))->assertOk()
        ->assertJsonPath('data.member.name', $this->plainMember->name)
        ->assertJsonPath('data.cycle.name', 'FY 2025/26')
        ->assertJsonCount(12, 'data.months')
        ->assertJsonPath('data.months.0.key', '2025-12')
        ->assertJsonPath('data.months.11.key', '2026-11')
        ->assertJsonPath('data.shares.total_shares', 5)
        ->assertJsonPath('data.shares.total_amount', 25000)
        ->assertJsonPath('data.contributions.grand_total', 25000)
        ->assertJsonPath('data.loans.totals.balance', 75000)
        ->assertJsonPath('data.loans.totals.paid', 40000);

    $shares = collect($response->json('data.shares.rows'))->keyBy('key');
    $funds = collect($response->json('data.contributions.funds'))->keyBy('name');

    expect($shares['2025-12']['shares'])->toBe(2)->and($shares['2026-11']['shares'])->toBe(3)->and($shares['2026-03']['shares'])->toBe(0)
        ->and($funds->keys()->sort()->values()->all())->toBe(['Jamii', 'Savings'])
        ->and($funds['Jamii']['months']['2026-02'])->toBe(5000)
        ->and($funds['Savings']['total'])->toBe(20000)
        ->and($response->json('data.loans.rows.0.loan_number'))->toBe($this->loan->loan_number)
        ->and($response->json('data.loans.rows.0.balance'))->toBe(75000)
        ->and($response->json('data.loans.rows.0.status'))->toBe('active');
});

test('a member with nothing recorded gets zeros and an empty loan list', function () {
    $this->actingAs($this->chair)->getJson(reportUrl('member-statement', $this, ['group_member_id' => $this->friendMember->id]))->assertOk()
        ->assertJsonPath('data.shares.total_shares', 1)
        ->assertJsonPath('data.contributions.grand_total', 0)
        ->assertJsonCount(0, 'data.contributions.funds')
        ->assertJsonCount(0, 'data.loans.rows')
        ->assertJsonPath('data.loans.totals.balance', 0);
});

test('a member only ever sees their own statement and outsiders see nothing', function () {
    $this->actingAs($this->plain)->getJson(reportUrl('member-statement', $this, ['group_member_id' => $this->friendMember->id]))->assertOk()
        ->assertJsonPath('data.member.id', $this->plainMember->id)->assertJsonPath('can_choose_member', false)->assertJsonCount(0, 'members');
    $this->actingAs($this->chair)->getJson(reportUrl('member-statement', $this, ['group_member_id' => $this->friendMember->id]))->assertOk()
        ->assertJsonPath('data.member.id', $this->friendMember->id)->assertJsonPath('can_choose_member', true);
    $this->actingAs($this->outsider)->getJson(reportUrl('member-statement', $this))->assertForbidden();
});

test('monthly shares lists each member with shares per month, total shares and total amount', function () {
    $response = $this->actingAs($this->chair)->getJson(reportUrl('monthly-shares', $this))->assertOk()
        ->assertJsonCount(12, 'data.months')
        ->assertJsonPath('data.total_shares', 6)
        ->assertJsonPath('data.grand_total', 30000)
        ->assertJsonPath('data.month_shares.2025-12', 2)
        ->assertJsonPath('data.month_shares.2026-01', 1);

    $rows = collect($response->json('data.rows'))->keyBy('member_id');

    expect($rows[$this->plainMember->id]['total_shares'])->toBe(5)
        ->and($rows[$this->plainMember->id]['total'])->toBe(25000)
        ->and($rows[$this->plainMember->id]['cells']['2026-11']['quantity'])->toBe(3);

    $own = $this->actingAs($this->plain)->getJson(reportUrl('monthly-shares', $this))->assertOk()->assertJsonCount(1, 'data.rows');

    expect($own->json('data.rows.0.member_id'))->toBe($this->plainMember->id)->and($own->json('data.total_shares'))->toBe(5);
});

test('monthly contributions are per fund, never offer the shares fund and default to the first fund', function () {
    $response = $this->actingAs($this->chair)->getJson(reportUrl('monthly-contributions', $this))->assertOk();

    expect(collect($response->json('funds'))->pluck('label')->all())->toBe(['Jamii', 'Loan Fund', 'Savings'])
        ->and($response->json('data.fund_id'))->toBe($this->social->id)
        ->and($response->json('data.grand_total'))->toBe(5000);

    $this->actingAs($this->chair)->getJson(reportUrl('monthly-contributions', $this, ['group_fund_id' => $this->fund->id]))->assertOk()
        ->assertJsonPath('data.grand_total', 20000)->assertJsonPath('data.totals.2026-01', 20000);
    $this->actingAs($this->plain)->getJson(reportUrl('monthly-contributions', $this, ['group_fund_id' => $this->fund->id]))->assertOk()->assertJsonCount(1, 'data.rows');
});

test('the loan statement lists loans with balances, filters by status and shows members only their own', function () {
    [, $other] = app(RecordExistingLoan::class)->handle(
        $this->group, $this->product, $this->chair,
        ['group_member_id' => $this->friendMember->id, 'requested_amount' => 50000, 'duration_months' => 3, 'purpose' => 'Test'],
        Carbon::parse('2026-03-05'), Carbon::parse('2026-03-05'),
    );
    $other->update(['status' => 'completed', 'outstanding_balance' => 0, 'amount_paid' => $other->total_payable]);

    $this->actingAs($this->chair)->getJson(route('reports.loan-statement', ['group_id' => $this->group->id]))->assertOk()
        ->assertJsonCount(2, 'data.rows')->assertJsonPath('data.totals.balance', 75000)->assertJsonPath('data.totals.principal', 150000);
    $this->actingAs($this->chair)->getJson(route('reports.loan-statement', ['group_id' => $this->group->id, 'status' => 'completed']))->assertOk()
        ->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.loan_number', $other->loan_number);
    $this->actingAs($this->plain)->getJson(route('reports.loan-statement', ['group_id' => $this->group->id]))->assertOk()
        ->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.loan_number', $this->loan->loan_number);
    $this->actingAs($this->outsider)->getJson(route('reports.loan-statement', ['group_id' => $this->group->id]))->assertForbidden();
});

test('every report can be downloaded as CSV with the same figures', function () {
    $statement = $this->actingAs($this->chair)->get(reportUrl('member-statement', $this, ['group_member_id' => $this->plainMember->id, 'format' => 'csv']))->assertOk()->assertDownload();
    $text = $statement->streamedContent();

    expect($text)->toContain('Member statement')->toContain('SHARES')->toContain('CONTRIBUTIONS')->toContain('LOANS')
        ->toContain('Dec 2025')->toContain('Shares,2,0,0,0,0,0,0,0,0,0,0,3,5')->toContain('Amount,10000,0,0,0,0,0,0,0,0,0,0,15000,25000')
        ->toContain($this->plainMember->name)->toContain('Jamii')->toContain($this->loan->loan_number)->toContain('75000');

    $shares = $this->actingAs($this->chair)->get(reportUrl('monthly-shares', $this, ['format' => 'csv']))->assertOk()->streamedContent();
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $shares))));

    expect($lines[1])->toContain('Name')->toContain('Dec 2025')->toContain('Total shares')->toContain('Total amount')
        ->and($shares)->toContain($this->plainMember->name)->and(end($lines))->toContain('Total')->toContain('30000');

    $this->actingAs($this->chair)->get(reportUrl('monthly-contributions', $this, ['format' => 'csv', 'group_fund_id' => $this->fund->id]))->assertOk()->assertDownload();
    expect($this->actingAs($this->chair)->get(route('reports.loan-statement', ['group_id' => $this->group->id, 'format' => 'csv']))->assertOk()->streamedContent())
        ->toContain('Loan statement')->toContain($this->loan->loan_number)->toContain('75000');
    $this->actingAs($this->outsider)->get(reportUrl('monthly-shares', $this, ['format' => 'csv']))->assertForbidden();
});

test('the Reports menu has the four reports and each page opens', function () {
    $menu = collect(config('sidebar.menus'))->firstWhere('label', 'Reports');

    expect(collect($menu['items'])->pluck('label')->all())->toBe(['Member Statement', 'Monthly Shares', 'Monthly Contributions', 'Loan Statement', 'Cash Reconciliation']);

    foreach (['member-statement' => 'report-member-statement', 'monthly-shares' => 'report-monthly-shares', 'monthly-contributions' => 'report-monthly-contributions', 'loan-statement' => 'report-loan-statement', 'cash-reconciliation' => 'report-cash-reconciliation'] as $route => $tag) {
        $this->actingAs($this->chair)->get(route("reports.{$route}"))->assertOk()->assertSee("<{$tag}", false);
    }
});

test('the cash reconciliation lists the figures like the group summary, with deposits covering every contribution', function () {
    $report = $this->actingAs($this->chair)->getJson(route('reports.cash-reconciliation', ['group_id' => $this->group->id]))->assertOk();
    $rows = collect($report->json('data.rows'))->pluck('value', 'label');

    // Contributions: shares 30,000 + savings 20,000 + social 5,000. The social fund is part of the deposits.
    expect($rows['Group Name'])->toBe($this->group->name)
        ->and($rows['Currency'])->toBe($this->group->currency)
        ->and($rows['Deposits'])->toEqual(55000)
        ->and($rows['Social Funds'])->toEqual(5000)
        ->and($rows['Withdraws'])->toEqual(100000)
        ->and($rows['Repayment'] + $rows['Penalty Paid'])->toEqual(40000)
        ->and($rows['Insurance'])->toEqual(0)
        ->and($rows['Dividends'])->toEqual(0)
        ->and($rows['Other'])->toEqual(0)
        ->and($rows['Expected Balance'])->toEqual(-5000);

    $this->actingAs($this->chair)->getJson(route('reports.cash-reconciliation', ['group_id' => $this->group->id, 'from' => '2026-04-01', 'to' => '2026-04-30']))
        ->assertJsonPath('data.balance', 40000);

    $this->actingAs($this->chair)->getJson(route('reports.cash-reconciliation', ['group_id' => $this->group->id, 'payment_method_id' => $this->group->paymentMethods()->value('id')]))
        ->assertJsonPath('data.opening.applies', false);

    expect($this->actingAs($this->chair)->get(route('reports.cash-reconciliation', ['group_id' => $this->group->id, 'format' => 'csv']))->assertOk()->streamedContent())
        ->toContain('Cash reconciliation')->toContain('"Social Funds",:,5000')->toContain('Expected Balance');

    $this->actingAs($this->plain)->getJson(route('reports.cash-reconciliation', ['group_id' => $this->group->id]))->assertForbidden();
    $this->actingAs($this->outsider)->getJson(route('reports.cash-reconciliation', ['group_id' => $this->group->id]))->assertForbidden();
});

test('insurance, membership fee and other contributions are told apart by fund, and each is part of the deposits', function () {
    $insurance = createFundFor($this->group, ['name' => 'Insurance', 'code' => 'INS']);
    $membership = createFundFor($this->group, ['name' => 'Membership Fee', 'code' => 'MEM']);
    $general = createFundFor($this->group, ['name' => 'General', 'code' => 'GEN']);

    foreach ([[$insurance, 1000], [$membership, 2000], [$general, 3000]] as [$fund, $amount]) {
        $id = $this->actingAs($this->chair)->postJson(route('contributions.store'), [
            'group_id' => $this->group->id, 'group_member_id' => $this->plainMember->id, 'payment_method_id' => $this->group->paymentMethods()->value('id'),
            'contribution_date' => '2026-05-10', 'period' => '2026-05', 'items' => [['group_fund_id' => $fund->id, 'amount' => $amount]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->chair)->patchJson(route('contributions.approve', $id))->assertOk();
    }

    $rows = collect($this->actingAs($this->chair)->getJson(route('reports.cash-reconciliation', ['group_id' => $this->group->id, 'from' => '2026-05-01', 'to' => '2026-05-31']))->json('data.rows'))->pluck('value', 'label');

    expect($rows['Deposits'])->toEqual(6000)->and($rows['Insurance'])->toEqual(1000)->and($rows['Membership Fee'])->toEqual(2000)->and($rows['Other Contribution'])->toEqual(3000);
});

test('the opening balance starts the expected balance, ignores earlier movements and is carried forward past a From date', function () {
    $this->group->ensureGroupSetting()->update(['opening_balance' => 100000, 'opening_balance_date' => '2026-01-01']);
    $url = fn (array $query = []) => route('reports.cash-reconciliation', ['group_id' => $this->group->id, ...$query]);

    // The December 2025 share (10,000) is before the opening date, so it is already part of the opening balance.
    $this->actingAs($this->chair)->getJson($url())
        ->assertJsonPath('data.opening.brought_forward', 100000)->assertJsonPath('data.balance', 85000);

    $this->actingAs($this->chair)->getJson($url(['from' => '2025-01-01']))->assertJsonPath('data.balance', 85000);

    $this->actingAs($this->chair)->getJson($url(['from' => '2026-04-01']))
        ->assertJsonPath('data.opening.brought_forward', 30000)->assertJsonPath('data.balance', 85000);

    $this->actingAs($this->chair)->getJson($url(['payment_method_id' => $this->group->paymentMethods()->value('id')]))
        ->assertJsonPath('data.opening.applies', false)->assertJsonPath('data.opening.brought_forward', 0);
});
