<template>
    <div class="card report">
        <div class="card-header no-print">
            <ReportToolbar title="Member statement" :groups="groups" :group-id="groupId" :cycles="cycles" :cycle-id="cycleId" :members="members" :member-id="memberId"
                :csv-url="csvUrl" @group="onGroup" @cycle="onCycle" @member="onMember" @print="print" />
        </div>

        <div v-if="report" class="card-body">
            <div class="report-header mb-3">
                <h4 class="mb-0">{{ report.group.name }}</h4>
                <div class="fs-5">Member statement</div>
                <div><strong>{{ report.member.name }}</strong> &middot; No. {{ report.member.member_number }} &middot; {{ report.member.phone }}</div>
                <div>Financial cycle: {{ report.cycle.name }} ({{ report.cycle.start_date }} to {{ report.cycle.end_date }})</div>
                <div class="small text-muted">Printed {{ new Date().toISOString().slice(0, 10) }} &middot; amounts in {{ currency }}</div>
            </div>

            <h5>Shares</h5>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm align-middle mb-0 report-table">
                    <thead><tr><th>Month</th><th class="text-end">Shares</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr v-for="row in report.shares.rows" :key="row.key">
                            <td>{{ row.label }}</td>
                            <td class="text-end">{{ row.shares || "" }}</td>
                            <td class="text-end">{{ row.amount > 0 ? money(row.amount) : "" }}</td>
                        </tr>
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td>Total</td><td class="text-end">{{ report.shares.total_shares }}</td><td class="text-end">{{ money(report.shares.total_amount) }}</td></tr></tfoot>
                </table>
            </div>

            <h5>Contributions</h5>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm align-middle text-center mb-0 report-table">
                    <thead>
                        <tr>
                            <th class="text-start">Fund</th>
                            <th v-for="month in report.months" :key="month.key">{{ month.label.split(" ")[0] }}<div class="small text-muted fw-normal">{{ month.key.slice(0, 4) }}</div></th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fund in report.contributions.funds" :key="fund.id">
                            <td class="text-start text-nowrap">{{ fund.name }}</td>
                            <td v-for="month in report.months" :key="month.key">{{ fund.months[month.key] > 0 ? money(fund.months[month.key]) : "" }}</td>
                            <td class="text-end fw-semibold">{{ money(fund.total) }}</td>
                        </tr>
                        <tr v-if="!report.contributions.funds.length"><td :colspan="report.months.length + 2" class="text-muted py-3">No contributions in this cycle.</td></tr>
                    </tbody>
                    <tfoot v-if="report.contributions.funds.length">
                        <tr class="fw-semibold">
                            <td class="text-start">Total</td>
                            <td v-for="month in report.months" :key="month.key">{{ report.contributions.totals[month.key] > 0 ? money(report.contributions.totals[month.key]) : "" }}</td>
                            <td class="text-end">{{ money(report.contributions.grand_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h5>Loans</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0 report-table">
                    <thead>
                        <tr>
                            <th>Loan</th><th>Product</th><th>Disbursed</th><th>Maturity</th>
                            <th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Total payable</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="loan in report.loans.rows" :key="loan.loan_number">
                            <td class="text-nowrap">{{ loan.loan_number }}</td>
                            <td>{{ loan.product }}</td>
                            <td>{{ loan.disbursement_date }}</td>
                            <td>{{ loan.maturity_date }}</td>
                            <td class="text-end">{{ money(loan.principal) }}</td>
                            <td class="text-end">{{ money(loan.interest) }}</td>
                            <td class="text-end">{{ money(loan.total_payable) }}</td>
                            <td class="text-end">{{ money(loan.paid) }}</td>
                            <td class="text-end fw-semibold">{{ money(loan.balance) }}</td>
                            <td><StatusBadge :status="loan.status" /></td>
                        </tr>
                        <tr v-if="!report.loans.rows.length"><td colspan="10" class="text-center text-muted py-3">No loans.</td></tr>
                    </tbody>
                    <tfoot v-if="report.loans.rows.length">
                        <tr class="fw-semibold">
                            <td colspan="4">Total</td>
                            <td class="text-end">{{ money(report.loans.totals.principal) }}</td>
                            <td></td>
                            <td class="text-end">{{ money(report.loans.totals.total_payable) }}</td>
                            <td class="text-end">{{ money(report.loans.totals.paid) }}</td>
                            <td class="text-end">{{ money(report.loans.totals.balance) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div v-else class="card-body text-center text-muted py-4">{{ loaded ? "Nothing to show: the group has no financial cycle or you are not a member." : "Loading..." }}</div>
    </div>
</template>

<script>
import reportPage from "../../utils/reportPage";

export default {
    mixins: [reportPage],

    data() {
        return { endpoint: "/reports/member-statement", members: [] };
    },

    methods: {
        afterLoad(data) {
            this.members = data.can_choose_member ? data.members : [];
            this.memberId = this.report?.member?.id ?? this.memberId;
        },
    },
};
</script>
