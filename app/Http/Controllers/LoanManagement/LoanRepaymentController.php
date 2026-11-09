<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\AllocateLoanRepayment;
use App\Actions\LoanManagement\RebuildLoanPayments;
use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\StoreLoanRepaymentRequest;
use App\Http\Requests\LoanManagement\UpdateLoanRepaymentRequest;
use App\Http\Resources\LoanManagement\LoanRepaymentResource;
use App\Models\LoanManagement\LoanRepayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanRepaymentController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['loan', 'groupMember', 'paymentMethod'];

    /**
     * Show the page, or the group's repayments as JSON: everyone's for leaders and the Treasurer, otherwise the viewer's own.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-repayments', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageLoans', $group);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $rows = LoanRepayment::query()
            ->whereHas('loan', fn ($loan) => $loan->where('group_id', $group->id))
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)))
            ->when(in_array($status, ['pending', 'approved'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('repayment_number', 'like', "%{$search}%")
                ->orWhereHas('loan', fn ($loan) => $loan->where('loan_number', 'like', "%{$search}%"))
                ->orWhereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $loans = $group->loans()
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $request->user()->id)));

        return LoanRepaymentResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'totals' => [
                'loan' => (float) (clone $loans)->sum('total_payable'),
                'repaid' => (float) (clone $loans)->sum('amount_paid'),
                'balance' => (float) (clone $loans)->sum('outstanding_balance'),
            ],
            'options' => $canManage ? [
                'loans' => $group->loans()->whereIn('status', ['active', 'overdue'])->with('groupMember:id,name')->orderBy('id')->get()
                    ->map(fn ($loan) => ['value' => $loan->id, 'label' => "{$loan->loan_number} - {$loan->groupMember->name} (owes ".number_format((float) $loan->outstanding_balance, 2).')', 'balance' => (float) $loan->outstanding_balance, 'disbursed' => $loan->disbursement_date->toDateString()])->all(),
                'payment_methods' => $this->options($group->paymentMethods()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ] : null,
        ])->response();
    }

    /**
     * Record one or several repayments (a date and an amount each) for the same loan. They are pending until approved.
     */
    public function store(StoreLoanRepaymentRequest $request): JsonResponse
    {
        $loan = $request->group()->loans()->findOrFail($request->validated('loan_id'));

        $repayments = DB::transaction(function () use ($request, $loan): array {
            return array_map(function (array $entry) use ($request, $loan): LoanRepayment {
                $date = Carbon::parse($entry['payment_date']);

                $repayment = LoanRepayment::query()->create([
                    'loan_id' => $loan->id,
                    'group_member_id' => $loan->group_member_id,
                    'payment_method_id' => $request->validated('payment_method_id'),
                    'payment_date' => $date->toDateString(),
                    'amount' => $entry['amount'],
                    'external_reference' => $entry['external_reference'],
                    'repayment_number' => 'PENDING-'.uniqid(),
                    'status' => 'pending',
                    'received_by' => $request->user()->id,
                ]);
                $repayment->update(['repayment_number' => sprintf('LR-%d-%06d', $date->year, $repayment->id)]);

                return $repayment;
            }, $request->entries());
        });

        foreach ($repayments as $repayment) {
            $repayment->load(self::RELATIONS);
        }

        return response()->json([
            'success' => true,
            'message' => count($repayments) > 1 ? count($repayments).' repayments recorded successfully.' : 'Repayment recorded successfully.',
            'data' => (new LoanRepaymentResource($repayments[0]))->resolve(),
            'records' => array_map(fn (LoanRepayment $repayment) => (new LoanRepaymentResource($repayment))->resolve(), $repayments),
        ], 201);
    }

    /**
     * Correct a repayment (date, amount, method, reference). An approved repayment is re-applied to the loan, so the installments,
     * balance and status follow, and its logged payment transaction is updated.
     */
    public function update(UpdateLoanRepaymentRequest $request, LoanRepayment $loanRepayment, RebuildLoanPayments $rebuild): JsonResponse
    {
        DB::transaction(function () use ($request, $loanRepayment, $rebuild): void {
            $loanRepayment->update($request->safe()->only(['payment_method_id', 'payment_date', 'amount', 'external_reference']));

            if ($loanRepayment->status === 'approved') {
                $loanRepayment->paymentTransactions()->update([
                    'payment_method_id' => $loanRepayment->payment_method_id,
                    'amount' => $loanRepayment->amount,
                    'provider_transaction_id' => $loanRepayment->external_reference,
                ]);

                $rebuild->handle($loanRepayment->loan);
            }
        });

        return $this->saved($loanRepayment->refresh(), 'Repayment updated successfully.');
    }

    /**
     * Delete a repayment. An approved one also leaves the loan's schedule: its payment transaction is removed and the installments,
     * balance and status are worked out again from the repayments that remain.
     */
    public function destroy(LoanRepayment $loanRepayment, RebuildLoanPayments $rebuild): JsonResponse
    {
        Gate::authorize('manageLoans', $loanRepayment->loan->group);

        DB::transaction(function () use ($loanRepayment, $rebuild): void {
            $loan = $loanRepayment->loan;
            $wasApproved = $loanRepayment->status === 'approved';

            $loanRepayment->paymentTransactions()->delete();
            $loanRepayment->delete();

            if ($wasApproved) {
                $rebuild->handle($loan);
            }
        });

        return response()->json(['success' => true, 'message' => 'Repayment deleted.']);
    }

    public function approve(Request $request, LoanRepayment $loanRepayment, AllocateLoanRepayment $allocate, RecordPaymentTransaction $record): JsonResponse
    {
        Gate::authorize('manageLoans', $loanRepayment->loan->group);

        if ($loanRepayment->status !== 'pending') {
            return $this->failure('Only a pending repayment can be approved.');
        }

        if ((float) $loanRepayment->amount > (float) $loanRepayment->loan->outstanding_balance) {
            return $this->failure('The repayment is more than the loan\'s outstanding balance.');
        }

        DB::transaction(function () use ($request, $loanRepayment, $allocate, $record): void {
            $loanRepayment->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $allocate->handle($loanRepayment);
            $record->handle($loanRepayment, $request->user());
        });

        return $this->saved($loanRepayment, 'Repayment approved.');
    }

    private function saved(LoanRepayment $repayment, string $message, int $status = 200): JsonResponse
    {
        $repayment->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new LoanRepaymentResource($repayment))->resolve()], $status);
    }
}
