<?php

namespace App\Http\Controllers\SettingManagement;

use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\SettingManagement\StoreFinePaymentRequest;
use App\Http\Requests\SettingManagement\StoreMemberFineRequest;
use App\Http\Requests\SettingManagement\WaiveMemberFineRequest;
use App\Http\Resources\SettingManagement\MemberFineResource;
use App\Models\PaymentManagement\FinePayment;
use App\Models\SettingManagement\MemberFine;
use App\Models\UserManagement\Group;
use App\Support\BillingPeriod;
use App\Support\ReportCsv;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberFineController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupMember', 'fineType'];

    /**
     * The Fines settings page, or the group's fines as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('payment-pages.show', ['page' => 'fines']);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('managePayments', $group);
        $query = $this->filteredQuery($request, $group, $canManage)
            ->with(self::RELATIONS)
            ->orderByDesc('fine_date')
            ->orderByDesc('id');

        $matching = $query->clone()->reorder()->toBase();
        $totals = [
            'count' => (clone $matching)->count(),
            'amount' => (float) (clone $matching)->sum('amount'),
            'balance' => (float) (clone $matching)->sum('balance'),
        ];

        $rows = $query->paginate(15)->withQueryString();

        return MemberFineResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'totals' => $totals,
            'filters' => [
                'members' => $canManage ? $group->groupMembers()->orderBy('name')->get(['id', 'name', 'member_number'])
                    ->map(fn ($member) => ['value' => $member->id, 'label' => "{$member->name} ({$member->member_number})"])->all() : [],
                'fine_types' => $this->options($group->fineTypes()->orderBy('name')),
                'cycles' => $group->financialCycles()
                    ->orderByRaw("case status when 'open' then 0 when 'draft' then 1 else 2 end")->orderByDesc('start_date')->get()
                    ->map(fn ($cycle) => ['value' => $cycle->id, 'label' => "{$cycle->name} ({$cycle->start_date->format('M Y')} - {$cycle->end_date->format('M Y')})"])->all(),
            ],
            'options' => $canManage ? [
                'members' => $this->memberOptions($group),
                'fine_types' => $group->fineTypes()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'default_amount', 'calculation_type'])
                    ->map(fn ($type) => ['value' => $type->id, 'label' => $type->name, 'default_amount' => (float) $type->default_amount, 'calculation_type' => $type->calculation_type])->all(),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ] : null,
        ])->response();
    }

    public function store(StoreMemberFineRequest $request, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $group = $request->group();
        $date = Carbon::parse($request->validated('fine_date'));

        $fine = DB::transaction(function () use ($request, $group, $date, $resolveCycle): MemberFine {
            $fine = $group->memberFines()->create([
                ...$request->validated(),
                'financial_cycle_id' => $resolveCycle->handle($group, $date, $request->user()->id)->id,
                'fine_number' => 'PENDING-'.uniqid(),
                'amount_paid' => 0,
                'balance' => $request->validated('amount'),
                'status' => 'pending',
                'issued_by' => $request->user()->id,
            ]);
            $fine->update(['fine_number' => sprintf('FN-%d-%06d', $date->year, $fine->id)]);

            return $fine;
        });

        return $this->saved($fine, 'Fine issued successfully.', 201);
    }

    public function waive(WaiveMemberFineRequest $request, MemberFine $memberFine): JsonResponse
    {
        if (! in_array($memberFine->status, ['pending', 'partial'], true)) {
            return $this->failure('Only an unpaid fine can be waived.');
        }

        $memberFine->update([
            'status' => 'waived',
            'balance' => 0,
            'waiver_reason' => $request->validated('waiver_reason'),
            'approved_by' => $request->user()->id,
        ]);

        return $this->saved($memberFine, 'Fine waived.');
    }

    public function pay(StoreFinePaymentRequest $request, MemberFine $memberFine, RecordPaymentTransaction $record): JsonResponse
    {
        if (! in_array($memberFine->status, ['pending', 'partial'], true)) {
            return $this->failure('This fine has nothing left to pay.');
        }

        DB::transaction(function () use ($request, $memberFine, $record): void {
            $payment = FinePayment::query()->create([
                ...$request->validated(),
                'member_fine_id' => $memberFine->id,
                'payment_number' => 'PENDING-'.uniqid(),
                'status' => 'approved',
                'received_by' => $request->user()->id,
            ]);
            $payment->update(['payment_number' => sprintf('FP-%d-%06d', now()->year, $payment->id)]);

            $paid = round((float) $memberFine->amount_paid + (float) $payment->amount, 2);
            $balance = max(0, round((float) $memberFine->amount - $paid, 2));

            $memberFine->update(['amount_paid' => $paid, 'balance' => $balance, 'status' => $balance <= 0 ? 'paid' : 'partial']);
            $record->handle($payment, $request->user());
        });

        return $this->saved($memberFine, 'Payment recorded.');
    }

    /**
     * The member fines CSV: one line per member with their total fine, paid fine and balance, then the overall totals.
     * It uses the same filters as the list.
     */
    public function export(Request $request): StreamedResponse
    {
        $group = $this->groupFor($request);
        $fines = $this->filteredQuery($request, $group, Gate::allows('managePayments', $group))
            ->with('groupMember:id,name')
            ->get(['id', 'group_member_id', 'amount', 'amount_paid', 'balance']);

        $rows = [['Name', 'Total fine', 'Paid fine', 'Balance']];

        foreach ($fines->groupBy('group_member_id')->sortBy(fn ($memberFines) => mb_strtolower($memberFines->first()->groupMember->name)) as $memberFines) {
            $rows[] = [$memberFines->first()->groupMember->name, ...$this->sums($memberFines)];
        }

        $rows[] = [];
        $rows[] = ['Overall total', ...$this->sums($fines)];

        return ReportCsv::download('member-fines-'.str($group->name)->slug().'.csv', $rows);
    }

    /**
     * @param  Collection<int, MemberFine>  $fines
     * @return array{0: string, 1: string, 2: string}
     */
    private function sums(Collection $fines): array
    {
        return [
            number_format((float) $fines->sum('amount'), 2, '.', ''),
            number_format((float) $fines->sum('amount_paid'), 2, '.', ''),
            number_format((float) $fines->sum('balance'), 2, '.', ''),
        ];
    }

    /**
     * The group's fines narrowed by the list filters (member, cycle, fine type, month, status and search). Members who cannot
     * manage payments only ever see their own.
     *
     * @return HasMany<MemberFine, Group>
     */
    private function filteredQuery(Request $request, Group $group, bool $canManage): HasMany
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $memberId = $canManage ? $request->integer('group_member_id') : 0;
        $cycleId = $request->integer('financial_cycle_id');
        $fineTypeId = $request->integer('fine_type_id');
        $month = BillingPeriod::monthFilter($request->string('month')->toString());

        return $group->memberFines()
            ->when($memberId > 0, fn ($query) => $query->where('group_member_id', $memberId))
            ->when($cycleId > 0, fn ($query) => $query->where('financial_cycle_id', $cycleId))
            ->when($fineTypeId > 0, fn ($query) => $query->where('fine_type_id', $fineTypeId))
            ->when($month !== null, fn ($query) => $query->whereBetween('fine_date', $month))
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'partial', 'paid', 'waived'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('fine_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))));
    }

    private function saved(MemberFine $fine, string $message, int $status = 200): JsonResponse
    {
        $fine->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new MemberFineResource($fine))->resolve()], $status);
    }
}
