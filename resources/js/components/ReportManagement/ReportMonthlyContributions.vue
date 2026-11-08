<template>
    <div class="card report">
        <div class="card-header no-print">
            <ReportToolbar title="Monthly contributions" :groups="groups" :group-id="groupId" :cycles="cycles" :cycle-id="cycleId" :funds="funds" :fund-id="fundId"
                :csv-url="csvUrl" @group="onGroup" @cycle="onCycle" @fund="onFund" @print="print" />
        </div>

        <div v-if="report" class="card-body">
            <div class="report-header mb-3">
                <h4 class="mb-0">{{ report.group.name }}</h4>
                <div>Monthly contributions &middot; {{ fundName }} &middot; {{ report.cycle.name }} ({{ report.cycle.start_date }} to {{ report.cycle.end_date }})</div>
                <div class="small text-muted">Printed {{ new Date().toISOString().slice(0, 10) }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center mb-0 report-table">
                    <thead>
                        <tr>
                            <th class="text-start">#</th>
                            <th class="text-start">Name</th>
                            <th v-for="month in report.months" :key="month.key">{{ month.label.split(" ")[0] }}<div class="small text-muted fw-normal">{{ month.key.slice(0, 4) }}</div></th>
                            <th class="text-end">Total ({{ currency }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.rows" :key="row.member_id">
                            <td class="text-start">{{ row.member_number }}</td>
                            <td class="text-start text-nowrap">{{ row.name }}</td>
                            <td v-for="month in report.months" :key="month.key">{{ row.cells[month.key].amount > 0 ? money(row.cells[month.key].amount) : "" }}</td>
                            <td class="text-end fw-semibold">{{ money(row.total) }}</td>
                        </tr>
                        <tr v-if="!report.rows.length"><td :colspan="report.months.length + 3" class="text-muted py-4">No members in this cycle.</td></tr>
                    </tbody>
                    <tfoot v-if="report.rows.length > 1">
                        <tr class="fw-semibold">
                            <td></td>
                            <td class="text-start">Total</td>
                            <td v-for="month in report.months" :key="month.key">{{ report.totals[month.key] > 0 ? money(report.totals[month.key]) : "" }}</td>
                            <td class="text-end">{{ money(report.grand_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div v-else class="card-body text-center text-muted py-4">{{ loaded ? "This group has no financial cycle yet." : "Loading..." }}</div>
    </div>
</template>

<script>
import reportPage from "../../utils/reportPage";

export default {
    mixins: [reportPage],

    data() {
        return { endpoint: "/reports/monthly-contributions", funds: [] };
    },

    computed: {
        fundName() {
            return this.funds.find((fund) => String(fund.value) === String(this.fundId))?.label ?? "";
        },
    },

    methods: {
        afterLoad(data) {
            this.funds = data.funds ?? [];
            this.fundId = this.report?.fund_id ?? this.funds[0]?.value ?? "";
        },
    },
};
</script>
