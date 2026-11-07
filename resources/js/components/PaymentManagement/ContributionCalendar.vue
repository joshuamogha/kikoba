<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-3"><h4 class="mb-0">{{ memberId ? "Monthly payments" : "Contribution calendar" }}</h4></div>
                <div v-if="!memberId" class="col-md-3">
                    <select :value="groupId" @change="onGroup($event.target.value)" class="form-select" aria-label="Group">
                        <option v-if="!groups.length" value="">No groups yet</option>
                        <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select v-model="cycleId" @change="load" class="form-select" aria-label="Financial cycle">
                        <option v-if="!cycles.length" value="">No financial cycles yet</option>
                        <option v-for="cycle in cycles" :key="cycle.value" :value="cycle.value">{{ cycle.label }}</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <div class="btn-group btn-group-sm">
                        <button v-for="option in types" :key="option.key" @click="type = option.key; load()" class="btn" :class="type === option.key ? 'btn-primary' : 'btn-outline-primary'">{{ option.label }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div v-if="calendar" class="table-responsive">
                <table class="table table-bordered table-sm mb-0 align-middle text-center calendar-table">
                    <thead>
                        <tr>
                            <th v-if="!memberId" class="text-start">Member</th>
                            <th v-for="month in calendar.months" :key="month.key">{{ month.label.split(" ")[0] }}<div class="small text-muted fw-normal">{{ month.key.slice(0, 4) }}</div></th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Missing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in calendar.rows" :key="row.member_id">
                            <td v-if="!memberId" class="text-start text-nowrap">
                                <a :href="`/members/${row.member_id}#monthly`">{{ row.name }}</a>
                                <div class="small text-muted">{{ row.member_number }}</div>
                            </td>
                            <td v-for="month in calendar.months" :key="month.key" :class="cellClass(row.cells[month.key].status)" :title="cellTitle(month, row.cells[month.key])">
                                <template v-if="row.cells[month.key].amount > 0">
                                    {{ money(row.cells[month.key].amount) }}
                                    <div v-if="type === 'shares'" class="small">{{ row.cells[month.key].quantity }} sh.</div>
                                </template>
                                <template v-else>{{ symbol(row.cells[month.key].status) }}</template>
                            </td>
                            <td class="fw-semibold">{{ money(row.total) }}</td>
                            <td>{{ row.months_paid + row.months_short }}</td>
                            <td :class="{ 'text-danger fw-semibold': row.months_missing }">{{ row.months_missing }}</td>
                        </tr>
                        <tr v-if="!calendar.rows.length"><td :colspan="calendar.months.length + 4" class="text-muted py-4">No members in this cycle.</td></tr>
                    </tbody>
                    <tfoot v-if="calendar.rows.length > 1">
                        <tr class="fw-semibold">
                            <td class="text-start">Total</td>
                            <td v-for="month in calendar.months" :key="month.key">{{ money(calendar.totals[month.key]) }}</td>
                            <td>{{ money(calendar.grand_total) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div v-else class="text-center text-muted py-4">{{ loaded ? "This group has no financial cycle yet. One is created with its first contribution." : "Loading..." }}</div>
        </div>

        <div v-if="calendar" class="card-footer small d-flex flex-wrap gap-3 justify-content-center">
            <span><span class="badge text-bg-success">&nbsp;</span> Paid</span>
            <span v-if="type === 'contributions' && calendar.expected > 0"><span class="badge text-bg-warning">&nbsp;</span> Short of {{ calendar.currency }} {{ money(calendar.expected) }} a month</span>
            <span><span class="badge text-bg-danger">&nbsp;</span> Missing</span>
            <span><span class="badge text-bg-light border">&nbsp;</span> Month not over</span>
            <span><span class="badge text-bg-secondary">&nbsp;</span> Not a member then</span>
        </div>
    </div>
</template>

<script>
import { initialGroup, rememberGroup } from "../../utils/paymentPage";

export default {
    props: {
        groups: { type: Array, default: () => [] },
        memberId: { type: Number, default: null },
        fixedGroupId: { type: Number, default: null },
    },

    data() {
        return {
            groupId: this.fixedGroupId ?? initialGroup(this.groups),
            cycleId: "",
            type: "contributions",
            types: [{ key: "contributions", label: "Contributions" }, { key: "shares", label: "Shares" }],
            cycles: [],
            calendar: null,
            loaded: false,
        };
    },

    created() {
        this.load();
    },

    methods: {
        load() {
            if (!this.groupId) {
                this.loaded = true;

                return;
            }

            axios
                .get("/contribution-calendar", { params: { group_id: this.groupId, financial_cycle_id: this.cycleId || undefined, type: this.type, group_member_id: this.memberId || undefined } })
                .then((response) => {
                    this.calendar = response.data.data;
                    this.cycles = response.data.cycles;
                    this.cycleId = this.calendar?.cycle.id ?? "";
                    this.loaded = true;
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        onGroup(groupId) {
            this.groupId = groupId;
            this.cycleId = "";
            rememberGroup(groupId);
            this.load();
        },

        cellClass(status) {
            return { paid: "table-success", short: "table-warning", missing: "table-danger", upcoming: "bg-body-tertiary text-muted", "n/a": "table-secondary text-muted" }[status];
        },

        symbol(status) {
            return { missing: "✕", upcoming: "·", "n/a": "–" }[status] ?? "";
        },

        cellTitle(month, cell) {
            return `${month.label}: ${{ paid: "paid", short: "paid less than expected", missing: "nothing paid", upcoming: "month not over", "n/a": "not a member" }[cell.status]}`;
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 0 }).format(value ?? 0);
        },
    },
};
</script>

<style scoped>
.calendar-table th,
.calendar-table td {
    min-width: 70px;
}
</style>
