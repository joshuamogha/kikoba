<template>
    <div class="card report">
        <div class="card-header no-print">
            <ReportToolbar title="Loan statement" :groups="groups" :group-id="groupId" :statuses="['active', 'overdue', 'completed']" :status="status" :csv-url="csvUrl"
                @group="onGroup" @status="onStatus" @print="print" />
        </div>

        <div v-if="report" class="card-body">
            <div class="report-header mb-3">
                <h4 class="mb-0">{{ report.group.name }}</h4>
                <div>Loan statement{{ status ? ` · ${status} loans` : "" }}</div>
                <div class="small text-muted">Printed {{ new Date().toISOString().slice(0, 10) }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0 report-table">
                    <thead>
                        <tr>
                            <th>Loan</th><th>Member</th><th>Product</th><th>Disbursed</th><th>Maturity</th>
                            <th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Penalty</th><th class="text-end">Total payable</th>
                            <th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.rows" :key="row.loan_number">
                            <td class="text-nowrap">{{ row.loan_number }}</td>
                            <td class="text-nowrap">{{ row.member }}<div class="small text-muted">{{ row.member_number }}</div></td>
                            <td>{{ row.product }}</td>
                            <td>{{ row.disbursement_date }}</td>
                            <td>{{ row.maturity_date }}</td>
                            <td class="text-end">{{ money(row.principal) }}</td>
                            <td class="text-end">{{ money(row.interest) }}</td>
                            <td class="text-end">{{ money(row.penalty) }}</td>
                            <td class="text-end">{{ money(row.total_payable) }}</td>
                            <td class="text-end">{{ money(row.paid) }}</td>
                            <td class="text-end fw-semibold">{{ money(row.balance) }}</td>
                            <td><StatusBadge :status="row.status" /></td>
                        </tr>
                        <tr v-if="!report.rows.length"><td colspan="12" class="text-center text-muted py-4">No loans found.</td></tr>
                    </tbody>
                    <tfoot v-if="report.rows.length">
                        <tr class="fw-semibold">
                            <td colspan="5">Total ({{ currency }})</td>
                            <td class="text-end">{{ money(report.totals.principal) }}</td>
                            <td class="text-end">{{ money(report.totals.interest) }}</td>
                            <td class="text-end">{{ money(report.totals.penalty) }}</td>
                            <td class="text-end">{{ money(report.totals.total_payable) }}</td>
                            <td class="text-end">{{ money(report.totals.paid) }}</td>
                            <td class="text-end">{{ money(report.totals.balance) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
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
        return { endpoint: "/reports/loan-statement" };
    },
};
</script>
