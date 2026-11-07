<template>
    <div v-if="member">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fs-4" style="width: 64px; height: 64px">{{ member.initials }}</span>
                        <div>
                            <h3 class="mb-1">{{ member.name }} <StatusBadge :status="member.membership_status" /></h3>
                            <div class="text-muted">{{ member.member_number }} &middot; {{ member.membership_type }} &middot; {{ member.group.name }}</div>
                            <div class="small mt-1">
                                <i class="bi bi-telephone"></i> {{ member.phone ?? "-" }}
                                <span v-if="member.email" class="ms-3"><i class="bi bi-envelope"></i> {{ member.email }}</span>
                                <span class="ms-3 badge" :class="member.has_account ? 'text-bg-success' : 'text-bg-secondary'">{{ member.has_account ? "Account linked" : "No account" }}</span>
                            </div>
                            <div class="small text-muted mt-1">
                                Joined {{ member.joined_at ?? "-" }}<span v-if="member.left_at"> &middot; Left {{ member.left_at }}<span v-if="member.exit_reason"> ({{ member.exit_reason }})</span></span>
                            </div>
                            <div class="mt-2"><span v-for="role in member.roles" :key="role" class="badge text-bg-info me-1">{{ role }}</span></div>
                        </div>
                    </div>
                    <a :href="`/members`" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> All members</a>
                </div>

                <div class="row g-3 mt-2 text-center">
                    <div v-for="tile in tiles" :key="tile.label" class="col-6 col-md-4 col-xl">
                        <div class="border rounded p-2 h-100">
                            <div class="text-muted small">{{ tile.label }}</div>
                            <div class="fs-5 fw-semibold">{{ tile.value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                <a href="#" class="nav-link" :class="{ active: tab.key === active }" @click.prevent="select(tab.key)">{{ tab.title }}</a>
            </li>
        </ul>

        <ContributionCalendar v-if="active === 'monthly'" :key="'monthly'" :member-id="memberId" :fixed-group-id="member.group.id" />

        <div v-else class="card">
            <div class="card-body">
                <MemberRecords :key="active" :member-id="memberId" :type="active" :columns="current.columns" />
            </div>
        </div>
    </div>
</template>

<script>
const tabs = [
    { key: "monthly", title: "Monthly", columns: [] },
    { key: "contributions", title: "Contributions", columns: [
        { key: "contribution_number", label: "Number" }, { key: "contribution_date", label: "Date" }, { key: "total_amount", label: "Total", kind: "money" },
        { key: "payment_method", label: "Method" }, { key: "status", label: "Status", kind: "status" },
    ] },
    { key: "shares", title: "Shares", columns: [
        { key: "transaction_number", label: "Number" }, { key: "transaction_date", label: "Date" }, { key: "shares", label: "Shares" },
        { key: "price_per_share", label: "Price", kind: "money" }, { key: "total_amount", label: "Total", kind: "money" }, { key: "status", label: "Status", kind: "status" },
    ] },
    { key: "loans", title: "Loan history", columns: [
        { key: "loan_number", label: "Loan" }, { key: "product", label: "Product" }, { key: "principal_amount", label: "Principal", kind: "money" },
        { key: "total_payable", label: "Total payable", kind: "money" }, { key: "amount_paid", label: "Paid", kind: "money" },
        { key: "outstanding_balance", label: "Outstanding", kind: "money" }, { key: "maturity_date", label: "Maturity" }, { key: "status", label: "Status", kind: "status" },
    ] },
    { key: "repayments", title: "Repayments", columns: [
        { key: "repayment_number", label: "Number" }, { key: "loan_number", label: "Loan" }, { key: "payment_date", label: "Date" },
        { key: "amount", label: "Amount", kind: "money" }, { key: "status", label: "Status", kind: "status" },
    ] },
    { key: "fines", title: "Fines", columns: [
        { key: "fine_number", label: "Number" }, { key: "fine_type", label: "Fine" }, { key: "reason", label: "Reason" }, { key: "fine_date", label: "Date" },
        { key: "amount", label: "Amount", kind: "money" }, { key: "balance", label: "Balance", kind: "money" }, { key: "status", label: "Status", kind: "status" },
    ] },
    { key: "transactions", title: "Transactions", columns: [
        { key: "transaction_number", label: "Number" }, { key: "type", label: "Type" }, { key: "date", label: "Date" },
        { key: "amount", label: "Amount", kind: "money" }, { key: "payment_method", label: "Method" }, { key: "status", label: "Status", kind: "status" },
    ] },
    { key: "guarantees", title: "Guarantees", columns: [
        { key: "application_number", label: "Application" }, { key: "borrower", label: "Borrower" },
        { key: "guaranteed_amount", label: "Guaranteed", kind: "money" }, { key: "status", label: "Status", kind: "status" },
    ] },
];

export default {
    props: {
        memberId: { type: Number, required: true },
    },

    data() {
        const hash = window.location.hash.replace("#", "");

        return { member: null, tabs, active: tabs.some((tab) => tab.key === hash) ? hash : tabs[0].key };
    },

    computed: {
        current() {
            return this.tabs.find((tab) => tab.key === this.active);
        },

        tiles() {
            const summary = this.member.summary;
            const currency = this.member.group.currency;
            const money = (value) => `${currency} ${new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value)}`;

            return [
                { label: "Contributions", value: money(summary.contributions_total) },
                { label: "Shares", value: `${summary.shares_count} (${money(summary.shares_amount)})` },
                { label: "Loans", value: summary.loans_count },
                { label: "Loan balance", value: money(summary.loans_outstanding) },
                { label: "Fines owed", value: money(summary.fines_balance) },
                { label: "Guaranteeing", value: summary.guarantees_count },
            ];
        },
    },

    created() {
        axios
            .get(`/members/${this.memberId}`)
            .then((response) => (this.member = response.data.data))
            .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
    },

    methods: {
        select(key) {
            this.active = key;
            window.history.replaceState(null, "", `#${key}`);
        },
    },
};
</script>
