<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan repayments" :groups="groups" :group-id="groupId" :statuses="['pending', 'approved']"
                :can-manage="canManage" add-label="Repayment" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div v-if="canManage" class="card-body py-2 border-bottom d-flex justify-content-between align-items-center">
            <span class="small text-muted">Past repayments on paper or in a spreadsheet? Import them against the loan numbers.</span>
            <button @click="importModal = true" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Import</button>
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
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                    <label class="form-label mb-0">Payments for this loan <small class="text-muted">(add a row for each date)</small></label>
                    <button @click="addPayment" type="button" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg"></i> Add another payment</button>
                </div>
                <div v-for="(payment, index) in form.payments" :key="index" class="row g-2 align-items-start border rounded pt-2 px-2 pb-0 mb-2">
                    <div class="col-md-4"><DatePicker v-model="payment.payment_date" label="Date" :error="errors[`payments.${index}.payment_date`]?.[0] ?? (index === 0 ? errors.payment_date?.[0] : '')" /></div>
                    <div class="col-md-3"><BaseInput v-model="payment.amount" type="number" min="0" step="0.01" label="Amount" :error="errors[`payments.${index}.amount`]?.[0] ?? (index === 0 ? errors.amount?.[0] : '')" /></div>
                    <div class="col-md-4"><BaseInput v-model="payment.external_reference" label="Reference (optional)" :error="errors[`payments.${index}.external_reference`]?.[0]" /></div>
                    <div class="col-md-1 d-flex align-items-center pt-3">
                        <button v-if="form.payments.length > 1" @click="removePayment(index)" type="button" class="btn btn-outline-danger btn-sm" title="Remove this payment"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div v-if="errors.payments?.[0]" class="text-danger small mb-1">{{ errors.payments[0] }}</div>
                <p class="small mb-0" :class="overBalance ? 'text-danger' : 'text-muted'">
                    Total of these payments: {{ money(paymentsTotal) }}<span v-if="selectedLoan"> &middot; the loan still owes {{ money(selectedLoan.balance) }}<span v-if="!overBalance">, {{ money(selectedLoan.balance - paymentsTotal) }} would remain</span><span v-else> (more than it owes)</span></span>
                </p>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">Save</button></template>
        </LargeModal>

        <ImportModal v-if="importModal" title="Import loan repayments" template-url="/loan-repayments/import/template" upload-url="/loan-repayments/import" :group-id="groupId"
            :fields="importFields" :columns="importColumns" key-label="Loan number"
            note="The template lists the group's open loans with their loan number, member and balance: fill in the amount, date and receipt for the repayments you have (the member and balance columns are only for reference). Rows are applied in date order, each as an approved repayment that pays the oldest installments first (penalty, then interest, then principal). A repayment cannot be more than the loan still owes, cannot be dated before the loan was disbursed, and a receipt that is already recorded is skipped, so the same file can be uploaded again safely. Import repayments before the daily overdue check runs on newly imported loans, or penalties will be added for installments that were really paid. Up to 500 rows per file."
            @close="importModal = false" @imported="load(1)" />
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/loan-repayments",
            importModal: false,
            importColumns: [
                { name: "loan_number", required: true, description: "The loan number from the Loans page, e.g. LN-2026-000012. It identifies the loan and its member.", example: "LN-2026-000012" },
                { name: "amount", required: true, description: "The amount the member paid. It cannot be more than the loan's outstanding balance.", example: "50000" },
                { name: "payment_date", required: true, description: "The date paid, e.g. 15/12/2025 08:32:47 or 2025-12-15 (the time is optional). Not before the loan was disbursed and not in the future.", example: "2026-02-05" },
                { name: "receipt", required: false, description: "The receipt number. It is unique: a receipt already recorded is skipped. Without one, the same loan, date and amount counts as a duplicate.", example: "RCT-0001" },
            ],
        };
    },

    computed: {
        selectedLoan() {
            return (this.options.loans ?? []).find((loan) => String(loan.value) === String(this.form.loan_id)) ?? null;
        },
        paymentsTotal() {
            return (this.form.payments ?? []).reduce((sum, payment) => sum + (Number(payment.amount) || 0), 0);
        },
        overBalance() {
            return this.selectedLoan !== null && Math.round(this.paymentsTotal * 100) > Math.round(this.selectedLoan.balance * 100);
        },
        importFields() {
            return [{ name: "payment_method_id", label: "Payment method (default: Cash)", type: "select", placeholder: "Cash / the group's first method", options: this.options.payment_methods ?? [] }];
        },
    },

    methods: {
        approve(item) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/approve`));
        },

        openAdd() {
            this.openForm({ loan_id: "", payment_method_id: "", payments: [this.blankPayment()] });
        },

        blankPayment() {
            return { payment_date: new Date().toISOString().slice(0, 10), amount: "", external_reference: "" };
        },

        addPayment() {
            this.form.payments.push(this.blankPayment());
        },

        removePayment(index) {
            this.form.payments.splice(index, 1);
        },

        save() {
            this.submit(axios.post(this.endpoint, { ...this.form, group_id: this.groupId }));
        },
    },
};
</script>
