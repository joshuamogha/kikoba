<?php

namespace App\Http\Controllers\LoanManagement;

use App\Actions\LoanManagement\ImportLoans;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\ImportLoansRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanImportController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * The CSV template for importing loans; guarantor columns are added when guarantors=1.
     */
    public function template(Request $request): StreamedResponse
    {
        $group = $this->groupFor($request);

        Gate::authorize('manageLoans', $group);

        $withGuarantors = $request->boolean('include_guarantors');
        $code = $group->loanProducts()->where('status', 'active')->orderBy('id')->value('code') ?? 'PRODUCT-CODE';

        return response()->streamDownload(function () use ($withGuarantors, $code): void {
            $headings = [...ImportLoans::REQUIRED, ...ImportLoans::OPTIONAL, ...($withGuarantors ? ImportLoans::guarantorColumns() : [])];
            $rows = [
                ['0712345678', $code, '500000', '2026-01-05', '2026-01-10', 'School fees', '6', ...($withGuarantors ? ['0754123456', '300000', '0765000111', '200000', '', ''] : [])],
                ['+255754123456', $code, '200000', '15/02/2026', '15/02/2026', 'Farm inputs', '', ...($withGuarantors ? ['', '', '', '', '', ''] : [])],
            ];

            $out = fopen('php://output', 'w');
            fputcsv($out, $headings, escape: '\\');

            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '\\');
            }

            fclose($out);
        }, 'loans-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Import loans from an uploaded CSV or Excel file.
     */
    public function import(ImportLoansRequest $request, ImportLoans $import): JsonResponse
    {
        try {
            $result = $import->handle($request->group(), $request->user(), $request->file('file'), $request->boolean('include_guarantors'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['imported'].' '.str('loan')->plural($result['imported']).' imported'.($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }
}
