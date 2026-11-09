<?php

namespace App\Http\Controllers\ReportManagement;

use App\Actions\ReportManagement\BuildCashReconciliation;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Support\ReportCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashReconciliationController extends Controller
{
    use ScopesReports;

    /**
     * The money in and out of the group in a period, to compare with the mobile money balance. Leaders and the Treasurer only.
     * A page, JSON or CSV (format=csv).
     */
    public function index(Request $request, BuildCashReconciliation $build): View|JsonResponse|StreamedResponse
    {
        if (! $request->expectsJson() && $request->query('format') !== 'csv') {
            return view('backend.pages.report-management.cash-reconciliation', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);

        Gate::authorize('managePayments', $group);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'payment_method_id' => ['nullable', 'integer'],
        ]);

        $from = $request->filled('from') ? $request->string('from')->toString() : null;
        $to = $request->filled('to') ? $request->string('to')->toString() : null;
        $method = $request->filled('payment_method_id') ? $group->paymentMethods()->findOrFail($request->integer('payment_method_id')) : null;

        $report = $build->handle($group, $from, $to, $method?->id);

        if ($request->query('format') === 'csv') {
            $rows = [
                [$group->name, 'Cash reconciliation', ($from ?? 'start').' to '.($to ?? 'today'), $method?->name ?? 'All payment methods', 'Printed', now()->toDateString()],
                [],
            ];

            foreach ($report['sections'] as $section) {
                $rows[] = [$section['title']];

                foreach ($section['rows'] as $row) {
                    $rows[] = ['', $row['label'], $row['amount']];
                }

                $rows[] = ['', 'Total', $section['total']];
                $rows[] = [];
            }

            $rows[] = ['Balance brought forward'.($report['opening']['as_of'] ? ' (opening balance as of '.$report['opening']['as_of'].')' : ''), '', $report['opening']['brought_forward']];
            $rows[] = ['Total money in', '', $report['summary']['money_in']];
            $rows[] = ['Withdrawals', '', $report['summary']['withdrawals']];
            $rows[] = ['Expected balance', '', $report['balance']];

            return ReportCsv::download('cash-reconciliation-'.str($group->name)->slug().'.csv', $rows);
        }

        return response()->json([
            'data' => ['group' => ['name' => $group->name, 'currency' => $group->currency], ...$report],
            'payment_methods' => $this->options($group->paymentMethods()->orderBy('display_order')->orderBy('id')),
        ]);
    }
}
