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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
            return redirect()->route('setting-pages.show', ['page' => 'fines']);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('managePayments', $group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = $group->memberFines()
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'partial', 'paid', 'waived'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('fine_number', 'like', "%{$search}%")
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%"))))
            ->orderByDesc('fine_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return MemberFineResource::collection($rows)->additional([
            'can_manage' => $canManage,
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

    private function saved(MemberFine $fine, string $message, int $status = 200): JsonResponse
    {
        $fine->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new MemberFineResource($fine))->resolve()], $status);
    }
}
