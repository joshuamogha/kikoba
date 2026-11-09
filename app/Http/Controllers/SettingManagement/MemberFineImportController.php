<?php

namespace App\Http\Controllers\SettingManagement;

use App\Actions\SettingManagement\ImportMemberFines;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\SettingManagement\ImportMemberFinesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberFineImportController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * The CSV template for importing member fines, identified by phone number and fine type name.
     */
    public function template(Request $request): StreamedResponse
    {
        Gate::authorize('managePayments', $this->groupFor($request));

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...ImportMemberFines::REQUIRED, ...ImportMemberFines::OPTIONAL], escape: '\\');
            fputcsv($out, ['0712345678', 'Absent', '2026-02-05', 'Missed the monthly meeting', '2000', '2026-03-05'], escape: '\\');
            fputcsv($out, ['+255754123456', 'Late', '15/03/2026', 'Arrived late to the meeting', '', ''], escape: '\\');
            fclose($out);
        }, 'member-fines-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Issue fines from an uploaded CSV or Excel file.
     */
    public function import(ImportMemberFinesRequest $request, ImportMemberFines $import): JsonResponse
    {
        try {
            $result = $import->handle($request->group(), $request->user(), $request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['imported'].' '.str('fine')->plural($result['imported']).' imported'.($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }
}
