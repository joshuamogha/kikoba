<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loans" :groups="groups" :group-id="groupId" :statuses="['active', 'overdue', 'completed']"
                :can-manage="canManage" add-label="Disburse" @group="onGroup" @search="onSearch" @status="onStatus"
                @add="openForm({ loan_application_id: '', disbursement_date: new Date().toISOString().slice(0, 10) })" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>Loan</th><th>Member</th><th class="text-end">Principal</th><th class="text-end">Total payable</th><th class="text-end">Outstanding</th><th>Maturity</th><th>Status</th><th>View</th></tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td>{{ item.loan_number }}<div class="small text-muted">{{ item.product }}</div></td>
                        <td>{{ item.member.name }}<div class="small text-muted">{{ item.member.member_number }}</div></td>
                        <td class="text-end">{{ money(item.principal_amount) }}</td>
                        <td class="text-end">{{ money(item.total_payable) }}<div class="small text-muted">Paid {{ money(item.amount_paid) }}</div></td>
                        <td class="text-end">{{ money(item.outstanding_balance) }}</td>
                        <td>{{ item.maturity_date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td><button @click="openDetail(item)" class="btn btn-info btn-sm" title="Schedule and repayments"><i class="bi bi-eye"></i></button></td>
                    </tr>
                    <tr v-if="!items.length"><td colspan="8" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <MediumModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>Disburse loan</h4></template>
            <template v-slot:body>
                <div v-if="!options.approved_applications?.length" class="alert alert-warning py-2">
                    There are no approved applications waiting. Approve one on the Loan Applications page first.
                </div>
                <BaseSelect v-model="form.loan_application_id" label="Approved application" :options="options.approved_applications" :error="errors.loan_application_id?.[0]" />
                <DatePicker v-model="form.disbursement_date" label="Disbursement date" :error="errors.disbursement_date?.[0]" />
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">Disburse</button></template>
        </MediumModal>

        <ExtraLargeModal v-if="detail" @close="detail = null">
            <template v-slot:header><h4>{{ detail.loan_number }} - {{ detail.member.name }}</h4></template>
            <template v-slot:body>
                <h6>Installments</h6>
                <table class="table table-sm table-striped">
                    <thead><tr><th>#</th><th>Due</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Penalty</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr v-for="row in detail.installments" :key="row.id">
                            <td>{{ row.installment_number }}</td><td>{{ row.due_date }}</td>
                            <td class="text-end">{{ money(row.principal_due) }}</td><td class="text-end">{{ money(row.interest_due) }}</td>
                            <td class="text-end">{{ money(row.penalty_due) }}</td><td class="text-end">{{ money(row.total_due) }}</td>
                            <td class="text-end">{{ money(row.total_paid) }}</td><td><StatusBadge :status="row.status" /></td>
                        </tr>
                    </tbody>
                </table>
                <h6 class="mt-3">Repayments</h6>
                <table class="table table-sm table-striped">
                    <thead><tr><th>Number</th><th>Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr v-for="row in detail.repayments" :key="row.id">
                            <td>{{ row.repayment_number }}</td><td>{{ row.payment_date }}</td><td class="text-end">{{ money(row.amount) }}</td><td><StatusBadge :status="row.status" /></td>
                        </tr>
                        <tr v-if="!detail.repayments.length"><td colspan="4" class="text-center text-muted">No repayments yet.</td></tr>
                    </tbody>
                </table>
            </template>
        </ExtraLargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/loans", detail: null };
    },

    methods: {
        save() {
            this.submit(axios.post(this.endpoint, { ...this.form, group_id: this.groupId }));
        },

        openDetail(item) {
            axios.get(`${this.endpoint}/${item.id}`).then((response) => (this.detail = response.data.data)).catch(this.handleError);
        },
    },
};
</script>
