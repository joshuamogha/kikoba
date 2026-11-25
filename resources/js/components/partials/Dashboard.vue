<template>
    <div>
        <!-- Platform admin figures -->
        <div v-if="platform" class="alert alert-light border d-flex flex-wrap align-items-center gap-3 py-2">
            <strong><i class="bi bi-shield-lock-fill text-danger"></i> Platform</strong>
            <span>{{ platform.groups }} groups</span>
            <span>{{ platform.users }} users ({{ platform.new_users }} new this week)</span>
            <a href="/site-settings" class="ms-auto btn btn-outline-secondary">Site settings</a>
        </div>

        <div v-if="!groups.length" class="card"><div class="card-body text-center text-muted py-5">You are not in a group yet.</div></div>

        <template v-else>
            <!-- Selectors -->
            <div class="row g-2 mb-3">
                <div class="col-12 col-md-4">
                    <select :value="groupId" @change="onGroup($event.target.value)" class="form-select" aria-label="Group">
                        <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <select :value="cycleId" @change="onCycle($event.target.value)" class="form-select" aria-label="Financial cycle">
                        <option value="all">All time</option>
                        <option v-for="cycle in cycles" :key="cycle.value" :value="cycle.value">{{ cycle.label }}</option>
                    </select>
                </div>
                <div v-if="isGroup" class="col-12 col-md-4 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
                    <a href="/contributions" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Contribution</a>
                    <a href="/loan-repayments" class="btn btn-info text-white"><i class="bi bi-cash-coin"></i> Repayment</a>
                    <a href="/group-expenses" class="btn btn-warning"><i class="bi bi-receipt"></i> Expense</a>
                    <a href="/reports/group-statement" class="btn btn-secondary"><i class="bi bi-journal-text"></i> Statement</a>
                </div>
            </div>

            <div v-if="!data" class="text-center text-muted py-5">{{ loaded ? "Nothing to show." : "Loading..." }}</div>

            <template v-else>
                <!-- Balance -->
                <div v-if="isGroup" class="row g-3 mb-3">
                    <div class="col-12 col-lg-5">
                        <div class="card h-100 text-bg-primary">
                            <div class="card-body">
                                <div class="small opacity-75">
                                    Group balance (cash, today)
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-white" title="How is this calculated?" aria-label="How is the group balance calculated?" @click="showBalance = true"><i class="bi bi-info-circle"></i></button>
                                </div>
                                <div class="display-5 fw-semibold">{{ currency }} {{ money(data.balance.amount) }}</div>
                                <div v-if="data.balance.opening" class="small opacity-75">Opening balance {{ money(data.balance.opening) }} as of {{ data.balance.opening_as_of }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-7">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="small text-muted mb-2">{{ cycleLabel }}</div>
                                <div class="row text-center">
                                    <div class="col-4"><div class="small text-muted">Money in</div><div class="fs-4 fw-semibold text-success">{{ money(data.balance.period_in) }}</div></div>
                                    <div class="col-4"><div class="small text-muted">Money out</div><div class="fs-4 fw-semibold text-danger">{{ money(data.balance.period_out) }}</div></div>
                                    <div class="col-4"><div class="small text-muted">Net</div><div class="fs-4 fw-semibold" :class="data.balance.period_net < 0 ? 'text-danger' : ''">{{ money(data.balance.period_net) }}</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key figures -->
                <div class="row g-3 mb-3">
                    <div v-for="card in cards" :key="card.label" class="col-6 col-md-4 col-xl-3">
                        <div class="card h-100"><div class="card-body py-3">
                            <div class="small text-muted"><i :class="`bi ${card.icon}`"></i> {{ card.label }}</div>
                            <div class="fs-4 fw-semibold" :class="card.danger ? 'text-danger' : ''">{{ card.value }}</div>
                            <div v-if="card.note" class="small text-muted">{{ card.note }}</div>
                        </div></div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row g-3 mb-3">
                    <div class="col-12" :class="isGroup ? 'col-lg-8' : ''">
                        <div class="card h-100"><div class="card-header">Contributions by month</div><div class="card-body">
                            <ApexChart v-if="data.charts.contributions.length" :key="`c-${chartKey}`" :options="contributionsChart" />
                            <div v-else class="text-center text-muted py-5">No contributions yet.</div>
                        </div></div>
                    </div>
                    <div v-if="isGroup" class="col-12 col-lg-4">
                        <div class="card h-100"><div class="card-header">Where the money is (by fund)</div><div class="card-body">
                            <ApexChart v-if="data.charts.fund_split.length" :key="`f-${chartKey}`" :options="fundChart" />
                            <div v-else class="text-center text-muted py-5">No contributions yet.</div>
                        </div></div>
                    </div>
                    <template v-if="isGroup">
                        <div class="col-12 col-lg-8">
                            <div class="card h-100"><div class="card-header">Money in and money out</div><div class="card-body"><ApexChart :key="`m-${chartKey}`" :options="moneyChart" /></div></div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card h-100"><div class="card-header">Collection this month <span v-if="data.charts.collection" class="text-muted small">({{ data.charts.collection.month }})</span></div><div class="card-body">
                                <ApexChart v-if="data.charts.collection && data.charts.collection.paid + data.charts.collection.not_paid" :key="`p-${chartKey}`" :options="collectionChart" />
                                <div v-else class="text-center text-muted py-5">Choose a financial cycle to see who has paid.</div>
                            </div></div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="card h-100"><div class="card-header">Loans given and repaid</div><div class="card-body"><ApexChart :key="`l-${chartKey}`" :options="loansChart" /></div></div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card h-100"><div class="card-header">Loans by status</div><div class="card-body">
                                <ApexChart v-if="loanStatusTotal" :key="`s-${chartKey}`" :options="loanStatusChart" />
                                <div v-else class="text-center text-muted py-5">No loans yet.</div>
                            </div></div>
                        </div>
                    </template>
                </div>

                <!-- Needs attention -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-6">
                        <div class="card h-100"><div class="card-header"><i class="bi bi-exclamation-circle text-warning"></i> Needs attention</div>
                            <ul class="list-group list-group-flush">
                                <li v-for="item in attentionLinks" :key="item.label" class="list-group-item d-flex justify-content-between align-items-center">
                                    <a :href="item.url" class="text-decoration-none">{{ item.label }}</a>
                                    <span class="badge" :class="item.danger ? 'text-bg-danger' : 'text-bg-secondary'">{{ item.value }}</span>
                                </li>
                                <li v-if="!attentionLinks.length" class="list-group-item text-muted">Nothing needs attention.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card h-100"><div class="card-header"><i class="bi bi-clock-history"></i> Coming up and overdue</div>
                            <ul class="list-group list-group-flush">
                                <li v-for="loan in data.attention.overdue_loans.items" :key="`o-${loan.number}`" class="list-group-item d-flex justify-content-between">
                                    <span><span class="badge text-bg-danger me-1">Overdue</span>{{ loan.number }} &middot; {{ loan.member }}</span><span>{{ money(loan.balance) }}</span>
                                </li>
                                <li v-for="row in data.attention.due_soon.items" :key="`d-${row.number}-${row.due}`" class="list-group-item d-flex justify-content-between">
                                    <span><span class="badge text-bg-warning me-1">Due {{ row.due }}</span>{{ row.number }} &middot; {{ row.member }}</span><span>{{ money(row.amount) }}</span>
                                </li>
                                <li v-if="data.attention.unpaid_members.length" class="list-group-item small">
                                    <strong>Not paid this month:</strong> {{ data.attention.unpaid_members.map((member) => member.name).join(", ") }}<span v-if="data.charts.collection.not_paid > data.attention.unpaid_members.length">, ...</span>
                                </li>
                                <li v-if="!data.attention.overdue_loans.items.length && !data.attention.due_soon.items.length && !data.attention.unpaid_members.length" class="list-group-item text-muted">Nothing is overdue or due this week.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Recent activity -->
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-activity"></i> Recent activity</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle text-nowrap">
                            <thead><tr><th>Date</th><th>Type</th><th>Number</th><th>Who</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <tr v-for="row in data.recent" :key="`${row.type}-${row.number}`">
                                    <td>{{ row.date }}</td><td>{{ row.type }}</td><td>{{ row.number }}</td><td>{{ row.who }}</td><td class="text-end">{{ money(row.amount) }}</td>
                                </tr>
                                <tr v-if="!data.recent.length"><td colspan="5" class="text-center text-muted py-3">No activity yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </template>
        <MediumModal v-if="showBalance && data?.balance" @close="showBalance = false">
            <template #header>How the group balance is calculated</template>
            <template #body>
                <p class="text-muted small mb-3">Cash movements up to today{{ data.balance.opening_as_of ? `, counted from ${data.balance.opening_as_of}` : "" }}. Only approved records count (expenses must be paid).</p>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-flag me-2 text-muted"></i>Opening balance / brought forward</span><span class="fw-semibold">{{ money(data.balance.brought_forward) }}</span>
                    </li>
                </ul>
                <div class="small text-uppercase fw-semibold text-success mb-1">Money in</div>
                <ul class="list-group list-group-flush mb-3">
                    <li v-for="line in balanceIn" :key="line.label" class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-plus-circle me-2 text-success"></i>{{ line.label }}</span><span class="text-success">{{ money(line.value) }}</span>
                    </li>
                </ul>
                <div class="small text-uppercase fw-semibold text-danger mb-1">Money out</div>
                <ul class="list-group list-group-flush mb-3">
                    <li v-for="line in balanceOut" :key="line.label" class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="bi bi-dash-circle me-2 text-danger"></i>{{ line.label }}</span><span class="text-danger">{{ money(line.value) }}</span>
                    </li>
                </ul>
                <div class="d-flex justify-content-between align-items-center rounded p-3 text-bg-primary">
                    <span class="fw-semibold">Group balance</span><span class="fs-5 fw-semibold">{{ currency }} {{ money(data.balance.amount) }}</span>
                </div>
            </template>
        </MediumModal>
    </div>
</template>

<script>
import { initialGroup, rememberGroup } from "../../utils/paymentPage";

const FALLBACK = ["#173d32", "#537454", "#f4b400", "#2f80ed", "#e5533d", "#8e6bbf", "#20c997", "#6c757d"];

export default {
    props: {
        groups: { type: Array, default: () => [] },
        platform: { type: Object, default: null },
    },

    data() {
        return { groupId: String(initialGroup(this.groups)), cycleId: "", cycles: [], data: null, loaded: false, chartKey: 0, showBalance: false };
    },

    computed: {
        isGroup() {
            return this.data?.scope === "group";
        },
        currency() {
            return this.data?.currency ?? "";
        },
        balanceIn() {
            const parts = this.data.balance.parts;

            return [
                { label: "Contributions (savings, shares, funds)", value: parts.contributions },
                { label: "Loan repayments (principal + interest)", value: parts.loan_repayments },
                { label: "Fines and loan penalties paid", value: parts.penalties_paid },
                { label: "Other income", value: parts.income },
            ];
        },
        balanceOut() {
            const parts = this.data.balance.parts;

            return [
                { label: "Loans given out", value: parts.loans_given },
                { label: "Expenses paid", value: parts.expenses },
                { label: "Dividends paid", value: parts.dividends },
            ];
        },
        cycleLabel() {
            return this.cycleId === "all" ? "All time" : (this.cycles.find((cycle) => String(cycle.value) === String(this.cycleId))?.label ?? "");
        },
        palette() {
            const brand = getComputedStyle(document.documentElement).getPropertyValue("--brand-primary").trim();
            const secondary = getComputedStyle(document.documentElement).getPropertyValue("--brand-secondary").trim();

            return [brand || FALLBACK[0], secondary || FALLBACK[1], ...FALLBACK.slice(2)];
        },
        loanStatusTotal() {
            const status = this.data?.charts.loan_status;

            return status ? status.active + status.overdue + status.completed : 0;
        },
        cards() {
            const cards = this.data.cards;

            if (!this.isGroup) {
                return [
                    { icon: "bi-pie-chart", label: "My shares", value: cards.shares, note: this.money(cards.shares_amount) },
                    { icon: "bi-wallet2", label: "My contributions", value: this.money(cards.contributions) },
                    { icon: "bi-exclamation-triangle", label: "Fines owed", value: this.money(cards.fines.balance), note: `paid ${this.money(cards.fines.paid)} of ${this.money(cards.fines.issued)}`, danger: cards.fines.balance > 0 },
                    { icon: "bi-bank", label: "Loan balance", value: this.money(cards.loans.balance), note: `borrowed ${this.money(cards.loans.principal)}, paid ${this.money(cards.loans.paid)}` },
                ];
            }

            return [
                { icon: "bi-people", label: "Active members", value: cards.members, note: `${cards.new_members} new this month` },
                { icon: "bi-pie-chart", label: "Shares", value: cards.shares.toLocaleString(), note: this.money(cards.shares_amount) },
                { icon: "bi-wallet2", label: "Contributions", value: this.money(cards.contributions), note: `${this.money(cards.contributions_month)} this month` },
                { icon: "bi-heart", label: "Social fund balance", value: this.money(cards.social_balance), danger: cards.social_balance < 0 },
                { icon: "bi-exclamation-triangle", label: "Fines paid", value: this.money(cards.fines.paid), note: `balance ${this.money(cards.fines.balance)}` },
                { icon: "bi-bank", label: "Loans balance", value: this.money(cards.loans.balance), note: `given ${this.money(cards.loans.principal)}, paid ${this.money(cards.loans.paid)}` },
                { icon: "bi-exclamation-octagon", label: "Overdue loans", value: cards.loans.overdue, note: `${cards.loans.active} active`, danger: cards.loans.overdue > 0 },
                { icon: "bi-graph-up-arrow", label: "Net profit", value: this.money(cards.profit.net), note: `gross ${this.money(cards.profit.gross)}`, danger: cards.profit.net < 0 },
            ];
        },
        attentionLinks() {
            const attention = this.data.attention;
            const links = [
                { label: "Contributions waiting for approval", url: "/contributions?status=pending", value: attention.pending_contributions.count },
                { label: "Loan repayments waiting for approval", url: "/loan-repayments", value: attention.pending_repayments },
                { label: "Incomes waiting for approval", url: "/group-incomes", value: attention.pending_incomes },
                { label: "Expenses to approve or pay", url: "/group-expenses", value: attention.pending_expenses },
                { label: "Overdue loans", url: "/loans", value: attention.overdue_loans.count, danger: true },
                { label: "Installments due this week", url: "/loan-installments", value: attention.due_soon.count },
                { label: `Unpaid fines (${this.money(attention.unpaid_fines.balance)})`, url: "/member-fines", value: attention.unpaid_fines.count },
            ];

            return links.filter((link) => link.value);
        },
        base() {
            return { chart: { toolbar: { show: false }, fontFamily: "inherit" }, colors: this.palette, dataLabels: { enabled: false }, legend: { position: "bottom" }, tooltip: { y: { formatter: (value) => this.money(value) } } };
        },
        contributionsChart() {
            return { ...this.base, chart: { ...this.base.chart, type: "bar", stacked: true, height: 300 }, series: this.data.charts.contributions, xaxis: { categories: this.data.charts.categories }, yaxis: { labels: { formatter: (value) => this.compact(value) } } };
        },
        fundChart() {
            const funds = this.data.charts.fund_split;

            return { ...this.base, chart: { type: "donut", height: 300 }, series: funds.map((fund) => fund.amount), labels: funds.map((fund) => fund.name) };
        },
        moneyChart() {
            const flow = this.data.charts.money_flow;

            return {
                ...this.base,
                chart: { ...this.base.chart, type: "line", height: 300 },
                colors: ["#198754", "#dc3545"],
                stroke: { width: [3, 3], curve: "smooth" },
                series: [{ name: "Money in", data: flow.in }, { name: "Money out", data: flow.out }],
                xaxis: { categories: this.data.charts.categories },
                yaxis: { labels: { formatter: (value) => this.compact(value) } },
            };
        },
        collectionChart() {
            const { paid, not_paid: notPaid } = this.data.charts.collection;

            return {
                ...this.base,
                chart: { type: "radialBar", height: 280 },
                colors: [this.palette[0]],
                series: [Math.round((paid / (paid + notPaid)) * 100)],
                labels: [`${paid} of ${paid + notPaid} paid`],
                plotOptions: { radialBar: { hollow: { size: "60%" }, dataLabels: { name: { fontSize: "13px" }, value: { fontSize: "26px", formatter: (value) => `${value}%` } } } },
            };
        },
        loansChart() {
            const flow = this.data.charts.loans_flow;

            return {
                ...this.base,
                chart: { ...this.base.chart, type: "bar", height: 300 },
                colors: [this.palette[2], this.palette[0]],
                series: [{ name: "Given out", data: flow.disbursed }, { name: "Repaid", data: flow.repaid }],
                xaxis: { categories: this.data.charts.categories },
                yaxis: { labels: { formatter: (value) => this.compact(value) } },
            };
        },
        loanStatusChart() {
            const status = this.data.charts.loan_status;

            return { ...this.base, chart: { type: "donut", height: 280 }, colors: ["#0d6efd", "#dc3545", "#198754"], series: [status.active, status.overdue, status.completed], labels: ["Active", "Overdue", "Completed"] };
        },
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
                .get("/dashboard", { params: { group_id: this.groupId, financial_cycle_id: this.cycleId || undefined } })
                .then((response) => {
                    this.data = response.data.data;
                    this.cycles = response.data.cycles;
                    this.cycleId = String(response.data.cycle_id);
                    this.chartKey++;
                    this.loaded = true;
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        onGroup(groupId) {
            this.groupId = groupId;
            this.cycleId = "";
            this.data = null;
            rememberGroup(groupId);
            this.load();
        },

        onCycle(cycleId) {
            this.cycleId = cycleId;
            this.load();
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 0 }).format(value ?? 0);
        },

        compact(value) {
            return new Intl.NumberFormat("en", { notation: "compact", maximumFractionDigits: 1 }).format(value ?? 0);
        },
    },
};
</script>
