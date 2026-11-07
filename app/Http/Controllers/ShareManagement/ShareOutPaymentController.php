<?php

namespace App\Http\Controllers\ShareManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShareManagement\StoreShareOutPaymentRequest;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use App\Models\ShareManagement\ShareOutPayment;
use App\Models\UserManagement\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShareOutPaymentController extends Controller
{
    /**
     * Record a payment made to one member of an approved share out.
     */
    public function store(StoreShareOutPaymentRequest $request, Group $group, ShareOut $shareOut): JsonResponse
    {
        if ($shareOut->status !== ShareOut::APPROVED) {
            return $this->failure('member_share_out_id', $shareOut->status === ShareOut::DRAFT
                ? 'Approve the share out before recording payments.'
                : 'This share out is already fully paid.');
        }

        $payment = DB::transaction(function () use ($request, $shareOut): ShareOutPayment|JsonResponse {
            // Lock the row so two payments recorded at once cannot pay more than is owed.
            $row = MemberShareOut::query()->lockForUpdate()->findOrFail($request->integer('member_share_out_id'));
            $balance = (float) $row->net_payable - (float) $row->amount_paid;
            $amount = round((float) $request->input('amount'), 2);

            if ($amount > $balance) {
                return $this->failure('amount', 'The amount is more than the '.number_format($balance, 2).' still owed to this member.');
            }

            $payment = $row->shareOutPayments()->create([
                'payment_number' => sprintf('%s-P%04d', $shareOut->share_out_number, ShareOutPayment::query()
                    ->whereIn('member_share_out_id', $shareOut->memberShareOuts()->select('id'))->count() + 1),
                'payment_date' => $request->input('payment_date'),
                'amount' => $amount,
                'payment_method_id' => $request->integer('payment_method_id'),
                'external_reference' => $request->input('external_reference'),
                'status' => ShareOutPayment::PAID,
                'approved_by' => $request->user()->id,
            ]);

            $paid = (float) $row->amount_paid + $amount;
            $settled = $paid >= (float) $row->net_payable;

            $row->update([
                'amount_paid' => $paid,
                'status' => $settled ? MemberShareOut::PAID : MemberShareOut::PARTIAL,
                'paid_at' => $settled ? now() : null,
            ]);

            $shareOut->completeWhenAllPaid();

            return $payment;
        });

        if ($payment instanceof JsonResponse) {
            return $payment;
        }

        return response()->json(['message' => 'Payment recorded.'], 201);
    }

    private function failure(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
