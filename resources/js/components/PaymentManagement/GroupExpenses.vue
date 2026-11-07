<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Group expenses" :groups="groups" :group-id="groupId" :statuses="statuses"
                :can-manage="canManage" add-label="Add expense" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Description</th><th>Fund</th><th>Category</th><th class="text-end">Amount</th><th>Date</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.expense_number }}</td>
                        <td>{{ item.description }}<div v-if="item.payee_name" class="small text-muted">To: {{ item.payee_name }}</div></td>
                        <td>{{ item.fund }}</td>
                        <td>{{ item.category }}<div class="small text-muted">{{ item.payment_method }}</div></td>
                        <td class="text-end">{{ money(item.amount) }}</td>
                        <td>{{ item.expense_date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td v-if="canManage">
                            <div class="btn-group btn-group-sm">
                                <button v-if="item.status === 'draft'" @click="move(item, 'approve')" class="btn btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                <button v-if="hasPay && item.status === 'approved'" @click="move(item, 'pay')" class="btn btn-primary" title="Mark as paid"><i class="bi bi-cash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 9 : 8" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>Add expense</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseSelect v-model="form.group_fund_id" label="Fund" :options="options.funds" :error="errors.group_fund_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.group_expense_category_id" label="Category" :options="options.categories" :error="errors.group_expense_category_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.payment_method_id" label="Payment method" :options="options.payment_methods" :error="errors.payment_method_id?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.expense_date" label="Date" :error="errors.expense_date?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.amount" type="number" min="0" step="0.01" label="Amount" :error="errors.amount?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-if="hasPay" v-model="form.payee_name" label="Payee" :error="errors.payee_name?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.description" label="Description" :error="errors.description?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.external_reference" label="Reference (optional)" :error="errors.external_reference?.[0]" /></div>
                </div>
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary" :disabled="saving">Save</button>
            </template>
        </LargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/group-expenses", statuses: ['draft', 'approved', 'paid'], hasPay: true };
    },

    methods: {
        openAdd() {
            this.openForm({ group_fund_id: "", group_expense_category_id: "", payment_method_id: "", expense_date: new Date().toISOString().slice(0, 10), amount: "", payee_name: "", description: "", external_reference: "" });
        },

        save() {
            const payload = { ...this.form, group_id: this.groupId };

            if (!this.hasPay) {
                delete payload.payee_name;
            }

            this.submit(axios.post(this.endpoint, payload));
        },

        move(item, action) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/${action}`));
        },
    },
};
</script>
