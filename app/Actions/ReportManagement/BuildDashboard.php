<?php

namespace App\Actions\ReportManagement;

use App\Actions\PaymentManagement\BuildContributionCalendar;
use App\Models\LoanManagement\LoanInstallment;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\ContributionItem;
use App\Models\PaymentManagement\FinancialCycle;
use App\Models\PaymentManagement\FinePayment;
use App\Models\ShareManagement\ShareOutPayment;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Support\Carbon;

class BuildDashboard
{
    public function __construct(private BuildCashReconciliation $cash, private BuildGroupStatement $statement, private BuildContributionCalendar $calendar) {}

    /**
     * What the dashboard shows for one group and financial cycle (null = all time). Leaders and the Treasurer ($manage) get the
     * group's balance, key figures, charts, things needing attention and recent activity. Everyone else gets their own figures
     * ($member) and no group balance.
     *
     * @return array<string, mixed>
     */
    public function handle(Group $group, ?FinancialCycle $cycle, ?GroupMember $member, bool $manage): array
    {
        $onlyMemberId = $manage ? null : ($member?->id ?? 0);
        $statement = $this->statement->handle($group, $cycle?->id, $onlyMemberId);
        $months = $this->months($cycle);
        $collection = $this->collection($group, $cycle, $onlyMemberId);

        return [
            'scope' => $manage ? 'group' : 'member',
            'currency' => $group->currency,
            'balance' => $manage ? $this->balance($group, $cycle) : null,
            'cards' => $manage ? $this->groupCards($group, $statement) : $this->memberCards($statement),
            'charts' => [
                'categories' => array_column($months, 'label'),
                'contributions' => $this->contributionSeries($group, $cycle, $months, $onlyMemberId),
                'money_flow' => $manage ? $this->moneyFlow($group, $months) : null,
                'fund_split' => $manage ? array_values(array_filter(array_map(fn (array $fund) => ['name' => $fund['name'], 'amount' => $fund['amount']], $statement['summary']['funds']), fn (array $fund) => $fund['amount'] > 0)) : [],
                'loans_flow' => $manage ? $this->loansFlow($group, $months) : null,
                'loan_status' => $manage ? ['active' => $statement['summary']['loans']['active'], 'overdue' => $statement['summary']['loans']['overdue'], 'completed' => $statement['summary']['loans']['completed']] : null,
                'collection' => $collection === null ? null : ['month' => $collection['month'], 'paid' => $collection['paid'], 'not_paid' => $collection['not_paid']],
            ],
            'attention' => $this->attention($group, $manage, $onlyMemberId, $collection),
            'recent' => $this->recent($group, $onlyMemberId),
        ];
    }

    /**
     * The group's cash balance now (the cash reconciliation, with the opening balance) and the money that came in and went out in the cycle.
     *
     * @return array<string, mixed>
     */
    private function balance(Group $group, ?FinancialCycle $cycle): array
    {
        $now = $this->cash->handle($group, null, today()->toDateString(), null);
        $period = $this->cash->handle($group, $cycle?->start_date->toDateString(), $cycle === null ? today()->toDateString() : min($cycle->end_date, today())->toDateString(), null);
        $out = $period['figures']['withdrawals'] + $period['figures']['dividends'];

        return [
            'amount' => $now['balance'],
            'opening' => $now['opening']['amount'],
            'opening_as_of' => $now['opening']['as_of'],
            'period_in' => $period['money_in'],
            'period_out' => round($out, 2),
            'period_net' => round($period['money_in'] - $out, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return array<string, mixed>
     */
    private function groupCards(Group $group, array $statement): array
    {
        $summary = $statement['summary'];
        $socialIds = $group->groupFunds()->where(fn ($query) => $query->where('code', 'SOC')->orWhereHas('fundType', fn ($type) => $type->where('code', 'SOC')))->pluck('id')->all();
        $socialIn = array_sum(array_map(fn (array $fund) => in_array($fund['id'], $socialIds, true) ? $fund['amount'] : 0, $summary['funds']));
        $socialOut = (float) $group->groupExpenses()->whereIn('group_fund_id', $socialIds)->whereIn('status', ['approved', 'paid'])->sum('amount');
        $thisMonth = (float) $group->contributions()->where('status', 'approved')->whereDate('contribution_date', '>=', today()->startOfMonth()->toDateString())->sum('total_amount');

        return [
            'members' => $group->groupMembers()->where('membership_status', 'active')->count(),
            'new_members' => $group->groupMembers()->whereDate('joined_at', '>=', today()->startOfMonth()->toDateString())->count(),
            'shares' => $summary['shares'],
            'shares_amount' => $summary['shares_amount'],
            'contributions' => $summary['total_contributions'],
            'contributions_month' => round($thisMonth, 2),
            'social_balance' => round($socialIn - $socialOut, 2),
            'fines' => $summary['fines'],
            'loans' => $summary['loans'],
            'profit' => ['gross' => $statement['profit']['gross']['total'], 'net' => $statement['profit']['net']['total']],
        ];
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return array<string, mixed>
     */
    private function memberCards(array $statement): array
    {
        $row = $statement['rows'][0] ?? null;

        return [
            'shares' => $row['shares'] ?? 0,
            'shares_amount' => $row['shares_amount'] ?? 0.0,
            'contributions' => $row['total_contributions'] ?? 0.0,
            'fines' => ['issued' => $row['fines_issued'] ?? 0.0, 'paid' => $row['fines_paid'] ?? 0.0, 'balance' => $row['fines_balance'] ?? 0.0],
            'loans' => ['principal' => $row['loans_principal'] ?? 0.0, 'paid' => $row['loans_paid'] ?? 0.0, 'balance' => $row['loans_balance'] ?? 0.0],
        ];
    }

    /**
     * The months charts run over: the cycle's months, or the last 12 months for all time.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function months(?FinancialCycle $cycle): array
    {
        if ($cycle !== null) {
            return $this->calendar->months($cycle);
        }

        return array_map(fn (int $back) => ['key' => today()->startOfMonth()->subMonths($back)->format('Y-m'), 'label' => today()->startOfMonth()->subMonths($back)->format('M Y')], range(11, 0));
    }

    /**
     * What was paid into each fund by month (approved contributions, by the month paid for).
     *
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array<int, array{name: string, data: array<int, float>}>
     */
    private function contributionSeries(Group $group, ?FinancialCycle $cycle, array $months, ?int $onlyMemberId): array
    {
        $keys = array_column($months, 'key');
        $series = [];

        ContributionItem::query()
            ->join('contributions', 'contributions.id', '=', 'contribution_items.contribution_id')
            ->join('group_funds', 'group_funds.id', '=', 'contribution_items.group_fund_id')
            ->where('contributions.group_id', $group->id)->where('contributions.status', 'approved')
            ->when($cycle !== null, fn ($query) => $query->where('contributions.financial_cycle_id', $cycle->id))
            ->when($onlyMemberId !== null, fn ($query) => $query->where('contributions.group_member_id', $onlyMemberId))
            ->whereRaw('substr(contribution_items.period_start, 1, 7) >= ?', [$keys[0]])
            ->whereRaw('substr(contribution_items.period_start, 1, 7) <= ?', [end($keys)])
            ->selectRaw('group_funds.name as fund, substr(contribution_items.period_start, 1, 7) as ym, sum(contribution_items.amount) as total')
            ->groupBy('group_funds.name', 'ym')->orderBy('group_funds.name')->get()
            ->each(function ($row) use (&$series, $keys): void {
                $series[$row->fund] ??= array_fill_keys($keys, 0.0);
                $series[$row->fund][$row->ym] = round((float) $row->total, 2);
            });

        return array_map(fn (string $name, array $data) => ['name' => $name, 'data' => array_values($data)], array_keys($series), $series);
    }

    /**
     * Money that came in and went out each month (approved or paid movements, like the cash reconciliation).
     *
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array{in: array<int, float>, out: array<int, float>}
     */
    private function moneyFlow(Group $group, array $months): array
    {
        [$from, $to] = $this->range($months);

        $in = [
            $this->monthly($group->contributions()->where('status', 'approved'), 'contribution_date', 'total_amount', $from, $to),
            $this->monthly(LoanRepayment::query()->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))->where('status', 'approved'), 'payment_date', 'amount', $from, $to),
            $this->monthly(FinePayment::query()->whereHas('memberFine', fn ($fine) => $fine->where('group_id', $group->id))->where('status', 'approved'), 'payment_date', 'amount', $from, $to),
            $this->monthly($group->groupIncomes()->where('status', 'approved'), 'income_date', 'amount', $from, $to),
            $this->monthly($group->memberShareTransactions()->where('transaction_type', 'purchase')->where('status', 'approved')->whereNull('contribution_id'), 'transaction_date', 'total_amount', $from, $to),
        ];
        $out = [
            $this->monthly($group->loans(), 'disbursement_date', 'principal_amount', $from, $to),
            $this->monthly($group->groupExpenses()->where('status', 'paid'), 'paid_at', 'amount', $from, $to),
            $this->monthly(ShareOutPayment::query()->whereHas('memberShareOut.shareOut', fn ($shareOut) => $shareOut->where('group_id', $group->id))->where('status', ShareOutPayment::PAID), 'payment_date', 'amount', $from, $to),
        ];

        return ['in' => $this->addUp($months, $in), 'out' => $this->addUp($months, $out)];
    }

    /**
     * Loans given out and repaid each month.
     *
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array{disbursed: array<int, float>, repaid: array<int, float>}
     */
    private function loansFlow(Group $group, array $months): array
    {
        [$from, $to] = $this->range($months);

        return [
            'disbursed' => $this->addUp($months, [$this->monthly($group->loans(), 'disbursement_date', 'principal_amount', $from, $to)]),
            'repaid' => $this->addUp($months, [$this->monthly(LoanRepayment::query()->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))->where('status', 'approved'), 'payment_date', 'amount', $from, $to)]),
        ];
    }

    /**
     * Who has paid this month (or the cycle's last month when it is over), from the contribution calendar.
     *
     * @return array{month: string, paid: int, not_paid: int, unpaid_members: array<int, array{name: string, member_number: string}>}|null
     */
    private function collection(Group $group, ?FinancialCycle $cycle, ?int $onlyMemberId): ?array
    {
        if ($cycle === null) {
            return null;
        }

        $grid = $this->calendar->handle($group, $cycle, 'contributions', $onlyMemberId);
        $keys = array_column($grid['months'], 'key');
        $key = in_array(today()->format('Y-m'), $keys, true) ? today()->format('Y-m') : end($keys);
        $paid = 0;
        $unpaid = [];

        foreach ($grid['rows'] as $row) {
            $status = $row['cells'][$key]['status'] ?? 'n/a';

            if (in_array($status, ['paid', 'short'], true)) {
                $paid++;
            } elseif (in_array($status, ['missing', 'upcoming'], true)) {
                $unpaid[] = ['name' => $row['name'], 'member_number' => $row['member_number']];
            }
        }

        return ['month' => Carbon::createFromFormat('!Y-m', $key)->format('F Y'), 'paid' => $paid, 'not_paid' => count($unpaid), 'unpaid_members' => array_slice($unpaid, 0, 8)];
    }

    /**
     * What needs a look: records waiting for approval, overdue loans, installments due this week, unpaid fines and members who have
     * not paid. Members see only their own.
     *
     * @param  array<string, mixed>|null  $collection
     * @return array<string, mixed>
     */
    private function attention(Group $group, bool $manage, ?int $onlyMemberId, ?array $collection): array
    {
        $own = fn ($query, string $relation = 'groupMember') => $query->when($onlyMemberId !== null, fn ($inner) => $inner->whereHas($relation, fn ($member) => $member->whereKey($onlyMemberId)));
        $pendingContributions = $own($group->contributions()->where('status', 'pending'));
        $overdueLoans = $own($group->loans()->where('status', 'overdue'));
        $dueSoon = $own(LoanInstallment::query()->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '>=', today()->toDateString())->whereDate('due_date', '<=', today()->addDays(7)->toDateString()), 'loan.groupMember');
        $unpaidFines = $own($group->memberFines()->whereIn('status', ['pending', 'partial']));

        return [
            'pending_contributions' => ['count' => (clone $pendingContributions)->count(), 'items' => (clone $pendingContributions)->with('groupMember:id,name')->orderBy('contribution_date')->limit(5)->get()
                ->map(fn ($row) => ['number' => $row->contribution_number, 'member' => $row->groupMember->name, 'amount' => (float) $row->total_amount, 'date' => $row->contribution_date->toDateString()])->all()],
            'pending_repayments' => $manage ? (clone LoanRepayment::query()->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))->where('status', 'pending'))->count() : null,
            'pending_incomes' => $manage ? $group->groupIncomes()->where('status', 'pending')->count() : null,
            'pending_expenses' => $manage ? $group->groupExpenses()->whereIn('status', ['draft', 'approved'])->count() : null,
            'overdue_loans' => ['count' => (clone $overdueLoans)->count(), 'items' => (clone $overdueLoans)->with('groupMember:id,name')->orderBy('maturity_date')->limit(5)->get()
                ->map(fn ($loan) => ['number' => $loan->loan_number, 'member' => $loan->groupMember->name, 'balance' => (float) $loan->outstanding_balance])->all()],
            'due_soon' => ['count' => (clone $dueSoon)->count(), 'items' => (clone $dueSoon)->with('loan.groupMember:id,name')->orderBy('due_date')->limit(5)->get()
                ->map(fn ($row) => ['number' => $row->loan->loan_number, 'member' => $row->loan->groupMember->name, 'due' => $row->due_date->toDateString(), 'amount' => round((float) $row->total_due - (float) $row->total_paid, 2)])->all()],
            'unpaid_fines' => ['count' => (clone $unpaidFines)->count(), 'balance' => round((float) (clone $unpaidFines)->sum('balance'), 2)],
            'unpaid_members' => $manage ? ($collection['unpaid_members'] ?? []) : [],
        ];
    }

    /**
     * The latest contributions, loan repayments and paid expenses.
     *
     * @return array<int, array{type: string, number: string, who: string, amount: float, date: string}>
     */
    private function recent(Group $group, ?int $onlyMemberId): array
    {
        $contributions = $group->contributions()->where('status', 'approved')->with('groupMember:id,name')
            ->when($onlyMemberId !== null, fn ($query) => $query->where('group_member_id', $onlyMemberId))->orderByDesc('contribution_date')->orderByDesc('id')->limit(10)->get()
            ->map(fn ($row) => ['type' => 'Contribution', 'number' => $row->contribution_number, 'who' => $row->groupMember->name, 'amount' => (float) $row->total_amount, 'date' => $row->contribution_date->toDateString()]);

        $repayments = LoanRepayment::query()->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))->where('status', 'approved')->with('groupMember:id,name')
            ->when($onlyMemberId !== null, fn ($query) => $query->where('group_member_id', $onlyMemberId))->orderByDesc('payment_date')->orderByDesc('id')->limit(10)->get()
            ->map(fn ($row) => ['type' => 'Loan repayment', 'number' => $row->repayment_number, 'who' => $row->groupMember->name, 'amount' => (float) $row->amount, 'date' => $row->payment_date->toDateString()]);

        $expenses = $onlyMemberId !== null ? collect() : $group->groupExpenses()->where('status', 'paid')->orderByDesc('expense_date')->orderByDesc('id')->limit(10)->get()
            ->map(fn ($row) => ['type' => 'Expense', 'number' => $row->expense_number, 'who' => $row->payee_name, 'amount' => (float) $row->amount, 'date' => $row->expense_date->toDateString()]);

        return $contributions->concat($repayments)->concat($expenses)->sortByDesc('date')->take(10)->values()->all();
    }

    /**
     * The sums of a query by month ("Y-m") between two dates.
     *
     * @param  BuilderContract<*>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $query
     * @return array<string, float>
     */
    private function monthly(mixed $query, string $dateColumn, string $amountColumn, string $from, string $to): array
    {
        return $query->whereDate($dateColumn, '>=', $from)->whereDate($dateColumn, '<=', $to)
            ->selectRaw("substr({$dateColumn}, 1, 7) as ym, coalesce(sum({$amountColumn}), 0) as total")
            ->groupBy('ym')->pluck('total', 'ym')->map(fn ($total) => (float) $total)->all();
    }

    /**
     * Adds monthly sums together, in the order of the months.
     *
     * @param  array<int, array{key: string, label: string}>  $months
     * @param  array<int, array<string, float>>  $sources
     * @return array<int, float>
     */
    private function addUp(array $months, array $sources): array
    {
        return array_map(fn (array $month) => round(array_sum(array_map(fn (array $source) => $source[$month['key']] ?? 0.0, $sources)), 2), $months);
    }

    /**
     * The first and last day covered by the months.
     *
     * @param  array<int, array{key: string, label: string}>  $months
     * @return array{0: string, 1: string}
     */
    private function range(array $months): array
    {
        return [
            Carbon::createFromFormat('!Y-m', $months[0]['key'])->startOfMonth()->toDateString(),
            Carbon::createFromFormat('!Y-m', end($months)['key'])->endOfMonth()->toDateString(),
        ];
    }
}
