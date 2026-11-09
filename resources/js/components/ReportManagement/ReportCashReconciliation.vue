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

            <table class="table table-bordered align-middle report-table mb-3">
                <tbody>
                    <tr v-for="row in report.rows" :key="row.label" :class="{ 'table-success fw-bold': row.label === 'Expected Balance' }">
                        <th class="w-50">{{ row.label }}</th>
                        <td class="text-center" style="width: 5%">:</td>
                        <td>{{ row.money ? money(row.value) : row.value }}</td>
                    </tr>
                </tbody>
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
