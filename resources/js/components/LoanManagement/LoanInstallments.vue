<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan installments" :groups="groups" :group-id="groupId" :statuses="['pending', 'partial', 'paid', 'overdue']"
                @group="onGroup" @search="onSearch" @status="onStatus" />
        </div>
        <div v-if="totals" class="card-body border-bottom">
            <div class="row text-center">
                <div class="col-4">
                    <div class="text-muted small">Total amount</div>
                    <div class="fs-4 fw-semibold">{{ money(totals.amount) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Total paid</div>
                    <div class="fs-4 fw-semibold">{{ money(totals.paid) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Balance</div>
                    <div class="fs-4 fw-semibold">{{ money(totals.balance) }}</div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>Loan</th><th>Member</th><th>#</th><th>Due</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Penalty</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td>{{ item.loan_number }}</td><td>{{ item.member }}</td><td>{{ item.installment_number }}</td><td>{{ item.due_date }}</td>
                        <td class="text-end">{{ money(item.principal_due) }}</td><td class="text-end">{{ money(item.interest_due) }}</td>
                        <td class="text-end">{{ money(item.penalty_due) }}</td><td class="text-end">{{ money(item.total_due) }}</td>
                        <td class="text-end">{{ money(item.total_paid) }}</td><td><StatusBadge :status="item.status" /></td>
                    </tr>
                    <tr v-if="!items.length"><td colspan="10" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/loan-installments" };
    },
};
</script>
