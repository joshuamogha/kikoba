<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreContributionRequest;
use App\Http\Resources\PaymentManagement\ContributionResource;
use App\Models\PaymentManagement\Contribution;
use App\Support\BillingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContributionController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupMember', 'paymentMethod', 'contributionItems.groupFund'];

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
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->contributions()
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'approved'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('contribution_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))))
            ->orderByDesc('contribution_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return ContributionResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => $canManage ? [
                'members' => $this->memberOptions($group),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
                'funds' => $this->options($group->groupFunds()->where('status', 'active')->orderBy('name')),
            ] : null,
        ])->response();
    }

    public function store(StoreContributionRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $group = $request->group();
        $date = Carbon::parse($request->validated('contribution_date'));
        $period = BillingPeriod::start($request->validated('period'), $date);

        $contribution = DB::transaction(function () use ($request, $group, $date, $period, $resolveCycle): Contribution {
            $contribution = $group->contributions()->create([
                ...$request->safe()->except(['items', 'period']),
                'financial_cycle_id' => $resolveCycle->handle($group, $period, $request->user()->id)->id,
                'contribution_number' => 'PENDING-'.uniqid(),
                'total_amount' => collect($request->validated('items'))->sum('amount'),
                'status' => 'pending',
                'received_by' => $request->user()->id,
            ]);

            $contribution->contributionItems()->createMany(
                collect($request->validated('items'))->map(fn (array $item) => [
                    'group_fund_id' => $item['group_fund_id'],
                    'amount' => $item['amount'],
                    'period_start' => $period->toDateString(),
                    'period_end' => $period->copy()->endOfMonth()->toDateString(),
                ])->all()
            );
            $contribution->update(['contribution_number' => sprintf('CON-%d-%06d', $date->year, $contribution->id)]);

            return $contribution;
        });

        return $this->saved($contribution, 'Contribution recorded successfully.', 201);
    }

    public function approve(Request $request, Contribution $contribution, RecordPaymentTransaction $record): JsonResponse
    {
        Gate::authorize('managePayments', $contribution->group);

        if ($contribution->status !== 'pending') {
            return $this->failure('Only a pending contribution can be approved.');
        }

        DB::transaction(function () use ($request, $contribution, $record): void {
            $contribution->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $record->handle($contribution, $request->user());
        });

        return $this->saved($contribution, 'Contribution approved.');
    }

    private function saved(Contribution $contribution, string $message, int $status = 200): JsonResponse
    {
        $contribution->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new ContributionResource($contribution))->resolve()], $status);
    }
}
