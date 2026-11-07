<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Actions\PaymentManagement\ImportContributions;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\ImportContributionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContributionImportController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * The CSV template for importing contributions to one fund, identified by phone number.
     */
    public function template(Request $request): StreamedResponse
    {
        Gate::authorize('managePayments', $this->groupFor($request));

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...ImportContributions::REQUIRED, ...ImportContributions::OPTIONAL], escape: '\\');
            fputcsv($out, ['0712345678', '20000', '2026-02-05', 'RCT-0001'], escape: '\\');
            fputcsv($out, ['+255754123456', '20000', '15/03/2026 08:32:47', 'RCT-0002'], escape: '\\');
            fclose($out);
        }, 'contributions-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Import contributions to the chosen fund from an uploaded CSV or Excel file.
     */
    public function import(ImportContributionsRequest $request, ImportContributions $import): JsonResponse
    {
        $group = $request->group();
        $fund = $group->groupFunds()->findOrFail($request->integer('group_fund_id'));
        $method = ($request->filled('payment_method_id') ? $group->paymentMethods()->find($request->integer('payment_method_id')) : null)
            ?? $group->paymentMethods()->where('is_active', true)->where('code', 'CASH')->first()
            ?? $group->paymentMethods()->where('is_active', true)->orderBy('id')->first();

        if ($method === null) {
            return response()->json(['message' => 'Add a payment method in Setting Management first.', 'errors' => ['payment_method_id' => ['Add a payment method first.']]], 422);
        }

        try {
            $result = $import->handle($group, $request->user(), $request->file('file'), $fund, $method);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['imported'].' '.str('contribution')->plural($result['imported']).' imported to '.$fund->name.($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }
}
