<template>
    <div class="card report">
        <div class="card-header no-print">
            <ReportToolbar title="Cash reconciliation" :groups="groups" :group-id="groupId" :csv-url="csvUrl" @group="onGroup" @print="print" />
            <div class="row g-2 mt-1">
                <div class="col-md-3"><DatePicker :model-value="from" label="From" @update:modelValue="onFrom" /></div>
                <div class="col-md-3"><DatePicker :model-value="to" label="To" @update:modelValue="onTo" /></div>
                <div class="col-md-3">
                    <label class="form-label">Payment method</label>
                    <select v-model="methodId" @change="load" class="form-select" aria-label="Payment method">
                        <option value="">All payment methods</option>
                        <option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="report" class="card-body">
            <div class="report-header mb-3">
                <h4 class="mb-0">{{ report.group.name }}</h4>
                <div>Cash reconciliation &middot; {{ from || "start" }} to {{ to || "today" }}</div>
                <div class="small text-muted">Printed {{ new Date().toISOString().slice(0, 10) }}</div>
            </div>

            <div class="row text-center g-3 mb-3">
                <div v-for="tile in tiles" :key="tile.label" class="col-6 col-md-3">
                    <div class="text-muted small">{{ tile.label }}</div>
                    <div class="fs-5 fw-semibold">{{ money(tile.value) }}</div>
                </div>
            </div>

            <table class="table table-bordered table-sm align-middle report-table">
                <tbody v-for="section in report.sections" :key="section.key">
                    <tr class="table-light fw-semibold"><td colspan="2">{{ section.title }}</td></tr>
                    <tr v-for="row in section.rows" :key="row.label"><td class="ps-4">{{ row.label }}</td><td class="text-end">{{ money(row.amount) }}</td></tr>
                    <tr v-if="!section.rows.length"><td colspan="2" class="ps-4 text-muted">Nothing recorded.</td></tr>
                    <tr class="fw-semibold"><td>Total</td><td class="text-end">{{ money(section.total) }}</td></tr>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Balance brought forward<span v-if="report.opening.as_of" class="fw-normal text-muted"> (opening balance as of {{ report.opening.as_of }})</span></td>
                        <td class="text-end">{{ money(report.opening.brought_forward) }}</td>
                    </tr>
                    <tr class="fw-semibold"><td>Total money in</td><td class="text-end">{{ money(report.summary.money_in) }}</td></tr>
                    <tr class="fw-semibold"><td>Withdrawals</td><td class="text-end">{{ money(report.summary.withdrawals) }}</td></tr>
                    <tr class="fw-bold table-success"><td>Expected balance ({{ currency }})</td><td class="text-end">{{ money(report.balance) }}</td></tr>
                </tfoot>
            </table>
            <p v-if="methodId" class="small text-muted mb-0">Loan disbursements have no payment method and the opening balance is one figure for the whole group, so neither is counted when a payment method is chosen.</p>
            <p v-else-if="!report.opening.as_of" class="small text-muted mb-0">No opening balance is set. Add one in the group's settings so the expected balance starts from what the group already held.</p>
        </div>
        <div v-else class="card-body text-center text-muted py-4">{{ loaded ? "Nothing to show." : "Loading..." }}</div>
    </div>
</template>

<script>
import reportPage from "../../utils/reportPage";

export default {
    mixins: [reportPage],

    data() {
        return { endpoint: "/reports/cash-reconciliation", from: "", to: "", methodId: "", methods: [] };
    },

    computed: {
        queryParams() {
            return { group_id: this.groupId, from: this.from || undefined, to: this.to || undefined, payment_method_id: this.methodId || undefined };
        },

        tiles() {
            const summary = this.report.summary;

            return [
                { label: "Deposits", value: summary.deposits },
                { label: "Withdraws", value: summary.withdrawals },
                { label: "Penalties charged", value: summary.penalties },
                { label: "Penalty paid", value: summary.penalties_paid },
                { label: "Social funds", value: summary.social_funds },
                { label: "Repayment", value: summary.repayments },
                { label: "Other income", value: summary.income },
                { label: "Expected balance", value: this.report.balance },
            ];
        },
    },

    methods: {
        onFrom(value) {
            this.from = value;
            this.load();
        },

        onTo(value) {
            this.to = value;
            this.load();
        },

        afterLoad(response) {
            this.methods = response.payment_methods ?? [];
        },
    },
};
</script>
