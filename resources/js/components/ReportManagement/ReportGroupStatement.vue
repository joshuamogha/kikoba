<template>
    <div class="card report">
        <div class="card-header no-print">
            <ReportToolbar title="Group statement" :groups="groups" :group-id="groupId" :cycles="cycles" :cycle-id="cycleId" :all-cycles="true"
                :export-columns="report?.export_columns ?? []" :export-url="endpoint" :export-params="queryParams" @group="onGroup" @cycle="onCycle" />
        </div>

        <div v-if="report" class="card-body">
            <div class="report-header mb-3">
                <h4 class="mb-0">{{ report.group.name }}</h4>
                <div>Group statement &middot; {{ report.cycle ? report.cycle.name : "All time" }}</div>
                <div class="small text-muted">Printed {{ new Date().toISOString().slice(0, 10) }} &middot; amounts in {{ currency }}</div>
            </div>

            <div v-if="report.checks.hisa_difference !== 0" class="alert alert-warning py-2">
                The Shares fund ({{ money(report.summary.funds.filter((fund) => fund.is_shares).reduce((sum, fund) => sum + fund.amount, 0)) }}) and the share records
                ({{ money(report.summary.shares_amount) }}) differ by {{ money(Math.abs(report.checks.hisa_difference)) }}. Use "Sync shares" on the Contributions page, or check the share purchases.
            </div>

            <div class="row g-3 mb-4 text-center">
                <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Total shares</div><div class="fs-4 fw-semibold">{{ report.summary.shares.toLocaleString() }}</div><div class="small">{{ money(report.summary.shares_amount) }}</div></div></div>
                <div v-for="fund in report.summary.funds" :key="fund.id" class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100"><div class="small text-muted">{{ fund.name }}</div><div class="fs-4 fw-semibold">{{ money(fund.amount) }}</div></div>
                </div>
                <div class="col-6 col-md-3"><div class="border rounded p-2 h-100 bg-body-tertiary"><div class="small text-muted">Total contributions</div><div class="fs-4 fw-semibold">{{ money(report.summary.total_contributions) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Fines paid</div><div class="fs-4 fw-semibold">{{ money(report.summary.fines.paid) }}</div><div class="small">of {{ money(report.summary.fines.issued) }} &middot; balance {{ money(report.summary.fines.balance) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Loans given ({{ report.summary.loans.count }})</div><div class="fs-4 fw-semibold">{{ money(report.summary.loans.principal) }}</div><div class="small">with interest {{ money(report.summary.loans.total_payable) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Loans paid</div><div class="fs-4 fw-semibold">{{ money(report.summary.loans.paid) }}</div></div></div>
                <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Loans balance</div><div class="fs-4 fw-semibold">{{ money(report.summary.loans.balance) }}</div><div class="small">{{ report.summary.loans.active }} active &middot; {{ report.summary.loans.overdue }} overdue &middot; {{ report.summary.loans.completed }} completed</div></div></div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0 report-table text-nowrap">
                    <thead>
                        <tr>
                            <th>Number</th><th>Name</th><th class="text-end">Shares</th><th class="text-end">Shares amount</th>
                            <th v-for="fund in report.funds" :key="fund.id" class="text-end">{{ fund.name }}</th>
                            <th class="text-end">Total</th><th class="text-end">Fines</th><th class="text-end">Fines paid</th>
                            <th class="text-end">Loans given</th><th class="text-end">Loans paid</th><th class="text-end">Loans balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.rows" :key="row.member_id">
                            <td>{{ row.member_number }}</td><td>{{ row.name }}</td>
                            <td class="text-end">{{ row.shares }}</td><td class="text-end">{{ money(row.shares_amount) }}</td>
                            <td v-for="fund in report.funds" :key="fund.id" class="text-end">{{ money(row[`fund_${fund.id}`]) }}</td>
                            <td class="text-end fw-semibold">{{ money(row.total_contributions) }}</td>
                            <td class="text-end">{{ money(row.fines_issued) }}</td><td class="text-end">{{ money(row.fines_paid) }}</td>
                            <td class="text-end">{{ money(row.loans_principal) }}</td><td class="text-end">{{ money(row.loans_paid) }}</td><td class="text-end fw-semibold">{{ money(row.loans_balance) }}</td>
                        </tr>
                        <tr v-if="!report.rows.length"><td :colspan="report.funds.length + 9" class="text-center text-muted py-4">No members found.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-else class="card-body text-center text-muted py-4">{{ loaded ? "Nothing to show." : "Loading..." }}</div>
    </div>
</template>

<script>
import reportPage from "../../utils/reportPage";

export default {
    mixins: [reportPage],

    data() {
        return { endpoint: "/reports/group-statement" };
    },
};
</script>
