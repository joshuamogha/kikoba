<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan ranges" :groups="groups" :group-id="groupId" :can-manage="canManage" add-label="Range"
                @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div class="card-body border-bottom py-2 small text-muted">
            A range ties a loan amount to a term. For example 0 to 100,000 = 1 month, 100,001 to 500,000 = 3 months. A product with ranges always gets
            its term from the amount, and the loan ends that many months after it is given. Products without ranges let the applicant choose the term.
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Product</th><th class="text-end">From amount</th><th class="text-end">To amount</th><th>Term</th><th>Repayment</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td><strong>{{ item.product }}</strong></td>
                        <td class="text-end">{{ money(item.minimum_amount) }}</td>
                        <td class="text-end">{{ item.maximum_amount === null ? "No limit" : money(item.maximum_amount) }}</td>
                        <td>{{ item.duration_months }} {{ item.duration_months === 1 ? "month" : "months" }}</td>
                        <td>{{ item.repayment_mode === "lump_sum" ? "One payment at the end" : "Installments" }}</td>
                        <td v-if="canManage">
                            <div class="btn-group btn-group-sm">
                                <button @click="openEdit(item)" class="btn btn-secondary" title="Edit"><i class="bi bi-pencil"></i></button>
                                <button @click="deleting = item" class="btn btn-danger" title="Remove"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 7 : 6" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <MediumModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit loan range" : "Add loan range" }}</h4></template>
            <template v-slot:body>
                <div v-if="!options.products?.length" class="alert alert-warning py-2">This group has no loan products yet. Add one on the Loan Products page first.</div>
                <BaseSelect v-model="form.loan_product_id" label="Loan product" :disabled="!!form.id" :options="options.products" :error="errors.loan_product_id?.[0]" />
                <BaseInput v-model="form.minimum_amount" type="number" min="0" step="0.01" label="From amount" :error="errors.minimum_amount?.[0]" />
                <BaseInput v-model="form.maximum_amount" type="number" min="0" step="0.01" label="To amount (empty = no limit)" :error="errors.maximum_amount?.[0]" />
                <BaseInput v-model="form.duration_months" type="number" min="1" label="Term (months)" :error="errors.duration_months?.[0]" />
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ? "Update" : "Save" }}</button></template>
        </MediumModal>

        <ConfirmModal v-if="deleting" @close="deleting = null">
            <template v-slot:header><h4>Alert</h4></template>
            <template v-slot:body>Remove the range {{ money(deleting.minimum_amount) }} to {{ deleting.maximum_amount === null ? "no limit" : money(deleting.maximum_amount) }} from {{ deleting.product }}? Loans already given keep their term.</template>
            <template v-slot:footer><button @click="destroy" class="btn btn-danger">Remove</button></template>
        </ConfirmModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/loan-ranges", deleting: null };
    },

    methods: {
        openAdd() {
            const last = this.items[this.items.length - 1];

            this.openForm({ loan_product_id: this.options.products?.[0]?.value ?? "", minimum_amount: last?.maximum_amount ? last.maximum_amount + 1 : 0, maximum_amount: "", duration_months: 1 });
        },

        openEdit(item) {
            this.openForm({ id: item.id, loan_product_id: item.loan_product_id, minimum_amount: item.minimum_amount, maximum_amount: item.maximum_amount ?? "", duration_months: item.duration_months });
        },

        save() {
            const payload = { ...this.form, group_id: this.groupId, maximum_amount: this.form.maximum_amount === "" ? null : this.form.maximum_amount };

            this.submit(this.form.id ? axios.put(`${this.endpoint}/${this.form.id}`, payload) : axios.post(this.endpoint, payload));
        },

        destroy() {
            const range = this.deleting;

            this.deleting = null;
            this.act(axios.delete(`${this.endpoint}/${range.id}`));
        },
    },
};
</script>
