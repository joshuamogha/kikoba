<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\ImportLoanRepayments;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\ImportLoanRepaymentsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanRepaymentImportController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * The CSV template for importing repayments, pre-filled with the group's open loans (loan number, member and balance) so the
     * leader only adds the amount, date and receipt. The member and balance columns are for reference and are ignored on import.
     */
    public function template(Request $request): StreamedResponse
    {
        $group = $this->groupFor($request);

        Gate::authorize('manageLoans', $group);

        $loans = $group->loans()->whereIn('status', ['active', 'overdue'])->with('groupMember:id,name')->orderBy('loan_number')->get();

        return response()->streamDownload(function () use ($loans): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...ImportLoanRepayments::REQUIRED, ...ImportLoanRepayments::OPTIONAL, 'member', 'outstanding_balance'], escape: '\\');

            if ($loans->isEmpty()) {
                fputcsv($out, ['LN-2026-000001', '50000', '2026-02-05', 'RCT-0001', 'Asha Juma', '600000'], escape: '\\');
            }

            foreach ($loans as $loan) {
                fputcsv($out, [$loan->loan_number, '', '', '', $loan->groupMember->name, $loan->outstanding_balance], escape: '\\');
            }

            fclose($out);
        }, 'loan-repayments-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Import repayments from an uploaded CSV or Excel file.
     */
    public function import(ImportLoanRepaymentsRequest $request, ImportLoanRepayments $import): JsonResponse
    {
        $group = $request->group();
        $method = ($request->filled('payment_method_id') ? $group->paymentMethods()->find($request->integer('payment_method_id')) : null)
            ?? $group->paymentMethods()->where('is_active', true)->where('code', 'CASH')->first()
            ?? $group->paymentMethods()->where('is_active', true)->orderBy('id')->first();

        if ($method === null) {
            return response()->json(['message' => 'Add a payment method in Setting Management first.', 'errors' => ['payment_method_id' => ['Add a payment method first.']]], 422);
        }

        try {
            $result = $import->handle($group, $request->user(), $request->file('file'), $method);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['imported'].' '.str('repayment')->plural($result['imported']).' imported'.($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }
}
