<?php

namespace App\Http\Controllers\ReportManagement;

use App\Actions\ReportManagement\BuildCashReconciliation;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportManagement\Concerns\ScopesReports;
use App\Models\UserManagement\Group;
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

        $rows = $this->rows($group, $report);

        if ($request->query('format') === 'csv') {
            return ReportCsv::download('cash-reconciliation-'.str($group->name)->slug().'.csv', [
                [$group->name, 'Cash reconciliation', ($from ?? 'start').' to '.($to ?? 'today'), $method?->name ?? 'All payment methods', 'Printed', now()->toDateString()],
                [],
                ...array_map(fn (array $row) => [$row['label'], ':', $row['value']], $rows),
            ]);
        }

        return response()->json([
            'data' => ['group' => ['name' => $group->name, 'currency' => $group->currency], 'rows' => $rows, 'opening' => $report['opening'], 'balance' => $report['balance']],
            'payment_methods' => $this->options($group->paymentMethods()->orderBy('display_order')->orderBy('id')),
        ]);
    }

    /**
     * The report as label / value lines, in the order they are shown and exported.
     *
     * @param  array{figures: array<string, float>, money_in: float, opening: array{brought_forward: float, as_of: string|null}, balance: float}  $report
     * @return array<int, array{label: string, value: string|float, money: bool}>
     */
    private function rows(Group $group, array $report): array
    {
        $figures = $report['figures'];
        $money = fn (string $label, float $value) => ['label' => $label, 'value' => $value, 'money' => true];

        return [
            ['label' => 'Group Name', 'value' => $group->name, 'money' => false],
            ['label' => 'Group ID', 'value' => $group->registration_number ?? (string) $group->id, 'money' => false],
            ['label' => 'Status', 'value' => strtoupper($group->status), 'money' => false],
            $money('Deposits', $figures['deposits']),
            $money('Withdraws', $figures['withdrawals']),
            $money('Penalties', $figures['penalties']),
            $money('Penalty Paid', $figures['penalties_paid']),
            $money('Social Funds', $figures['social_funds']),
            $money('Repayment', $figures['repayments']),
            $money('Insurance', $figures['insurance']),
            $money('Other Contribution', $figures['other_contribution']),
            $money('Membership Fee', $figures['membership_fee']),
            $money('Dividends', $figures['dividends']),
            $money('Other', $figures['other']),
            ['label' => 'Currency', 'value' => $group->currency, 'money' => false],
            $money('Balance Brought Forward'.($report['opening']['as_of'] ? " (opening balance as of {$report['opening']['as_of']})" : ''), $report['opening']['brought_forward']),
            $money('Expected Balance', $report['balance']),
        ];
    }
}
