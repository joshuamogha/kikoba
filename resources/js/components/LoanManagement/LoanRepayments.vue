<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan repayments" :groups="groups" :group-id="groupId" :statuses="['pending', 'approved']"
                :can-manage="canManage" add-label="Repayment" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Loan</th><th>Member</th><th class="text-end">Amount</th><th>Split</th><th>Date</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.repayment_number }}</td>
                        <td>{{ item.loan_number }}</td>
                        <td>{{ item.member }}</td>
                        <td class="text-end">{{ money(item.amount) }}<div class="small text-muted">{{ item.payment_method }}</div></td>
                        <td class="small">
                            <div v-if="item.status === 'approved'">Principal {{ money(item.principal_amount) }}</div>
                            <div v-if="item.status === 'approved'">Interest {{ money(item.interest_amount) }}</div>
                            <div v-if="item.status === 'approved' && item.penalty_amount">Penalty {{ money(item.penalty_amount) }}</div>
                        </td>
                        <td>{{ item.payment_date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td v-if="canManage">
                            <button v-if="item.status === 'pending'" @click="approve(item)" class="btn btn-success btn-sm" title="Approve"><i class="bi bi-check-lg"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 9 : 8" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>Add repayment</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseSelect v-model="form.loan_id" label="Loan" :options="options.loans" :error="errors.loan_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.payment_method_id" label="Payment method" :options="options.payment_methods" :error="errors.payment_method_id?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.amount" type="number" min="0" step="0.01" label="Amount" :error="errors.amount?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.payment_date" label="Date" :error="errors.payment_date?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.external_reference" label="Reference (optional)" :error="errors.external_reference?.[0]" /></div>
                </div>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">Save</button></template>
        </LargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/loan-repayments" };
    },

    methods: {
        approve(item) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/approve`));
        },

        openAdd() {
            this.openForm({ loan_id: "", payment_method_id: "", amount: "", payment_date: new Date().toISOString().slice(0, 10), external_reference: "" });
        },

        save() {
            this.submit(axios.post(this.endpoint, { ...this.form, group_id: this.groupId }));
        },
    },
};
</script>
