<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Actions\ShareManagement\RecordSharePurchase;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreContributionRequest;
use App\Http\Requests\PaymentManagement\StoreManyContributionsRequest;
use App\Http\Requests\PaymentManagement\UpdateContributionRequest;
use App\Http\Resources\PaymentManagement\ContributionResource;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\ContributionItem;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Support\BillingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ContributionController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = [
        'groupMember', 'paymentMethod', 'contributionItems.groupFund', 'financialCycle:id,name', 'receiver:id,name', 'approver:id,name',
        'paymentTransactions:id,reference_type,reference_id,transaction_number', 'shareTransactions:id,contribution_id,transaction_number,number_of_shares',
    ];

    private const SHARE_LINKED_MESSAGE = 'This contribution records a share purchase. Change the share purchase on the Shares page instead.';

    /**
     * Show the page, or the group's contributions as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.contributions', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = $this->canManagePayments($group);
        $request->attributes->set('can_manage', $canManage);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $memberId = $canManage ? $request->integer('group_member_id') : 0;
        $cycleId = $request->integer('financial_cycle_id');
        $fundId = $request->integer('group_fund_id');
        $month = BillingPeriod::monthFilter($request->string('month')->toString());

        $query = $group->contributions()
            ->with(self::RELATIONS)
            ->when($memberId > 0, fn ($query) => $query->where('group_member_id', $memberId))
            ->when($cycleId > 0, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->when($fundId > 0, fn ($query) => $query->whereHas('contributionItems', fn ($item) => $item->where('group_fund_id', $fundId)))
            ->when($month !== null, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('contributionItems', fn ($item) => $item->whereBetween('period_start', $month))
                ->orWhere(fn ($noPeriod) => $noPeriod->whereDoesntHave('contributionItems', fn ($item) => $item->whereNotNull('period_start'))
                    ->whereBetween('contribution_date', $month))))
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'approved'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('contribution_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))))
            ->orderByDesc('contribution_date')
            ->orderByDesc('id');

        $matching = $query->clone()->reorder()->toBase();
        $totals = [
            'count' => (clone $matching)->count(),
            // With a fund chosen, only that fund's share of each contribution is counted.
            'amount' => (float) ($fundId > 0
                ? ContributionItem::query()->whereIn('contribution_id', (clone $matching)->select('contributions.id'))->where('group_fund_id', $fundId)->sum('amount')
                : (clone $matching)->sum('total_amount')),
        ];

        $rows = $query->paginate(15)->withQueryString();

        return ContributionResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'totals' => $totals,
            'filters' => [
                'members' => $canManage ? $group->groupMembers()->orderBy('name')->get(['id', 'name', 'member_number'])
                    ->map(fn ($member) => ['value' => $member->id, 'label' => "{$member->name} ({$member->member_number})"])->all() : [],
                'funds' => $this->options($group->groupFunds()->orderBy('name')),
                'cycles' => $group->financialCycles()
                    ->orderByRaw("case status when 'open' then 0 when 'draft' then 1 else 2 end")->orderByDesc('start_date')->get()
                    ->map(fn ($cycle) => ['value' => $cycle->id, 'label' => "{$cycle->name} ({$cycle->start_date->format('M Y')} - {$cycle->end_date->format('M Y')})"])->all(),
            ],
            'options' => $canManage ? [
                'members' => $this->memberOptions($group),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'funds' => $this->options($group->groupFunds()->where('status', 'active')->orderBy('name')),
            ] : null,
        ])->response();
    }

    public function store(StoreContributionRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $date = Carbon::parse($request->validated('contribution_date'));
        $period = BillingPeriod::start($request->validated('period'), $date);

        $contribution = DB::transaction(fn (): Contribution => $this->createPending(
            $request->group(), $request->user(), $resolveCycle, $date, $period, $request->safe()->except(['items', 'period']), $request->validated('items'),
        ));

        return $this->saved($contribution, 'Contribution recorded successfully.', 201);
    }

    /**
     * Record one pending contribution for each member in the batch, all with the same date, month and payment method.
     */
    public function storeMany(StoreManyContributionsRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $group = $request->group();
        $date = Carbon::parse($request->validated('contribution_date'));
        $period = BillingPeriod::start($request->validated('period'), $date);

        $contributions = DB::transaction(fn (): array => collect($request->validated('rows'))->map(fn (array $row): Contribution => $this->createPending(
            $group, $request->user(), $resolveCycle, $date, $period,
            ['group_member_id' => $row['group_member_id'], 'payment_method_id' => $request->validated('payment_method_id'), 'contribution_date' => $request->validated('contribution_date'), 'external_reference' => $row['external_reference'] ?? null],
            $row['items'],
        ))->all());

        return response()->json([
            'success' => true,
            'message' => count($contributions).' '.str('contribution')->plural(count($contributions)).' recorded.',
            'data' => ['count' => count($contributions)],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{group_fund_id: int|string, amount: int|float|string}>  $items
     */
    private function createPending(Group $group, User $user, ResolveFinancialCycle $resolveCycle, Carbon $date, Carbon $period, array $attributes, array $items): Contribution
    {
        $contribution = $group->contributions()->create([
            ...$attributes,
            'financial_cycle_id' => $resolveCycle->handle($group, $period, $user->id)->id,
            'contribution_number' => 'PENDING-'.uniqid(),
            'total_amount' => collect($items)->sum('amount'),
            'status' => 'pending',
            'received_by' => $user->id,
        ]);

        $contribution->contributionItems()->createMany(
            collect($items)->map(fn (array $item) => [
                'group_fund_id' => $item['group_fund_id'],
                'amount' => $item['amount'],
                'period_start' => $period->toDateString(),
                'period_end' => $period->copy()->endOfMonth()->toDateString(),
            ])->all()
        );
        $contribution->update(['contribution_number' => sprintf('CON-%d-%06d', $date->year, $contribution->id)]);

        return $contribution;
    }

    /**
     * One contribution with everything about it: managers may open any of the group's, a member only their own.
     */
    public function show(Request $request, Contribution $contribution): JsonResponse
    {
        $canManage = $this->canManagePayments($contribution->group);

        abort_unless($canManage || $contribution->groupMember->user_id === $request->user()->id, 403);

        $request->attributes->set('can_manage', $canManage);

        return $this->saved($contribution, 'Contribution loaded.');
    }

    /**
     * Correct a contribution: member, date, month, method, reference and the amount per fund. An approved contribution keeps its
     * status and its logged payment transaction is updated to match.
     */
    public function update(UpdateContributionRequest $request, Contribution $contribution, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        if ($contribution->shareTransactions()->exists()) {
            return $this->failure(self::SHARE_LINKED_MESSAGE);
        }

        $request->attributes->set('can_manage', true);

        $items = collect($request->validated('items'));
        $date = Carbon::parse($request->validated('contribution_date'));
        $period = BillingPeriod::start($request->validated('period'), $date);

        DB::transaction(function () use ($request, $contribution, $resolveCycle, $items, $period): void {
            $contribution->update([
                ...$request->safe()->only(['group_member_id', 'payment_method_id', 'contribution_date', 'external_reference', 'notes']),
                'financial_cycle_id' => $resolveCycle->handle($contribution->group, $period, $request->user()->id)->id,
                'total_amount' => $items->sum('amount'),
            ]);

            $contribution->contributionItems()->delete();
            $contribution->contributionItems()->createMany($items->map(fn (array $item) => [
                'group_fund_id' => $item['group_fund_id'],
                'amount' => $item['amount'],
                'period_start' => $period->toDateString(),
                'period_end' => $period->copy()->endOfMonth()->toDateString(),
            ])->all());

            $contribution->paymentTransactions()->update([
                'group_member_id' => $contribution->group_member_id,
                'payment_method_id' => $contribution->payment_method_id,
                'amount' => $contribution->total_amount,
                'provider_transaction_id' => $contribution->external_reference,
            ]);
        });

        return $this->saved($contribution->refresh(), 'Contribution updated successfully.');
    }

    /**
     * Delete a contribution with its items and its logged payment transaction.
     */
    public function destroy(Contribution $contribution): JsonResponse
    {
        Gate::authorize('managePayments', $contribution->group);

        if ($contribution->shareTransactions()->exists()) {
            return $this->failure(self::SHARE_LINKED_MESSAGE);
        }

        DB::transaction(function () use ($contribution): void {
            $contribution->paymentTransactions()->delete();
            $contribution->contributionItems()->delete();
            $contribution->delete();
        });

        return response()->json(['success' => true, 'message' => 'Contribution deleted.']);
    }

    /**
     * Approve a contribution. A Shares fund item also records the shares it buys.
     */
    public function approve(Request $request, Contribution $contribution, RecordPaymentTransaction $record, RecordSharePurchase $recordShares): JsonResponse
    {
        Gate::authorize('managePayments', $contribution->group);

        if ($contribution->status !== 'pending') {
            return $this->failure('Only a pending contribution can be approved.');
        }

        try {
            $this->approveOne($contribution, $request->user(), $record, $recordShares);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->saved($contribution, 'Contribution approved.');
    }

    /**
     * Approve several pending contributions of one group at once. Ones that are not pending, or whose shares do not fit, are skipped.
     */
    public function approveMany(Request $request, RecordPaymentTransaction $record, RecordSharePurchase $recordShares): JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $group = $this->groupFor($request);

        Gate::authorize('managePayments', $group);

        $approved = 0;

        $group->contributions()->whereIn('id', $data['ids'])->where('status', 'pending')->orderBy('id')->each(function (Contribution $contribution) use ($request, $record, $recordShares, &$approved): void {
            try {
                $this->approveOne($contribution, $request->user(), $record, $recordShares);
                $approved++;
            } catch (InvalidArgumentException) {
                // Its shares do not fit: it stays pending and is counted as skipped.
            }
        });

        $skipped = count($data['ids']) - $approved;

        return response()->json([
            'success' => true,
            'message' => $approved.' '.str('contribution')->plural($approved).' approved'.($skipped > 0 ? ", {$skipped} skipped (not pending or shares do not fit)." : '.'),
            'data' => ['approved' => $approved, 'skipped' => $skipped],
        ]);
    }

    /**
     * Record the shares for approved contributions to the Shares fund that have none yet (for example ones approved before shares
     * were recorded from contributions). Amounts that are not a whole number of shares, or that do not fit, are skipped.
     */
    public function syncShares(Request $request, RecordSharePurchase $recordShares): JsonResponse
    {
        $group = $this->groupFor($request);

        Gate::authorize('managePayments', $group);

        $synced = 0;
        $skipped = 0;

        $group->contributions()
            ->where('status', 'approved')
            ->whereDoesntHave('shareTransactions')
            ->whereHas('contributionItems.groupFund.fundType', fn ($type) => $type->where('code', 'SHR'))
            ->with(['approver', 'groupMember', 'group'])
            ->orderBy('id')
            ->each(function (Contribution $contribution) use ($request, $recordShares, &$synced, &$skipped): void {
                try {
                    $records = DB::transaction(fn (): array => $recordShares->recordFromContribution($contribution, $contribution->approver ?? $request->user()));
                } catch (InvalidArgumentException) {
                    $records = [];
                }

                $records === [] ? $skipped++ : $synced++;
            });

        return response()->json([
            'success' => true,
            'message' => $synced.' '.str('contribution')->plural($synced).' synced to member shares'.($skipped > 0 ? ", {$skipped} skipped (not a whole number of shares, or no room)." : '.'),
            'data' => ['synced' => $synced, 'skipped' => $skipped],
        ]);
    }

    /**
     * @throws InvalidArgumentException when the shares of a Shares fund item do not fit
     */
    private function approveOne(Contribution $contribution, User $approver, RecordPaymentTransaction $record, RecordSharePurchase $recordShares): void
    {
        DB::transaction(function () use ($contribution, $approver, $record, $recordShares): void {
            $contribution->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
            $record->handle($contribution, $approver);
            $recordShares->recordFromContribution($contribution, $approver);
        });
    }

    private function saved(Contribution $contribution, string $message, int $status = 200): JsonResponse
    {
        $contribution->load(self::RELATIONS);

        request()->attributes->set('can_manage', request()->attributes->get('can_manage', $this->canManagePayments($contribution->group)));

        return response()->json(['success' => true, 'message' => $message, 'data' => (new ContributionResource($contribution))->resolve()], $status);
    }
}
