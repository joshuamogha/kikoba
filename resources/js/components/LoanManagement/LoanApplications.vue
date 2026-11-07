<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan applications" :groups="groups" :group-id="groupId" :statuses="['submitted', 'approved', 'rejected', 'cancelled']"
                :can-manage="true" add-label="Apply" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div v-if="canManage" class="card-body py-2 border-bottom d-flex justify-content-between align-items-center">
            <span class="small text-muted">Already gave a loan? Record it in one step: application, guarantors (optional) and loan.</span>
            <div class="btn-group btn-group-sm">
                <button @click="openRecord" class="btn btn-outline-primary"><i class="bi bi-journal-plus"></i> Record existing loan</button>
                <button @click="importModal = true" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Import loans</button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Member</th><th>Product</th><th class="text-end">Amount</th><th>Term / ends</th><th>Guarantors</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.application_number }}<div class="small text-muted">{{ item.application_date }}</div></td>
                        <td>{{ item.member.name }}<div class="small text-muted">{{ item.purpose }}</div></td>
                        <td>{{ item.product }}<div class="small text-muted">{{ item.interest_rate }}%</div></td>
                        <td class="text-end">{{ money(item.requested_amount) }}<div v-if="item.approved_amount !== null" class="small text-success">Approved {{ money(item.approved_amount) }}</div></td>
                        <td>
                            {{ item.duration_months }} {{ item.duration_months === 1 ? "month" : "months" }}
                            <div v-if="item.end_date" class="small" :class="item.end_date_is_expected ? 'text-muted' : ''">
                                {{ item.end_date_is_expected ? "Ends " + item.end_date + " if given today" : "Ends " + item.end_date }}
                            </div>
                        </td>
                        <td>{{ item.guarantors_accepted }} / {{ item.guarantors_required }}</td>
                        <td><StatusBadge :status="item.status" /><div v-if="item.rejection_reason" class="small text-muted">{{ item.rejection_reason }}</div></td>
                        <td>
                            <div v-if="item.status === 'submitted'" class="btn-group btn-group-sm">
                                <button v-if="canManage" @click="openDecision('approve', item)" class="btn btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                <button v-if="canManage" @click="openDecision('reject', item)" class="btn btn-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                <button v-if="canManage || item.is_own" @click="cancel(item)" class="btn btn-secondary" title="Cancel"><i class="bi bi-slash-circle"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td colspan="9" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>Loan application</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><MemberSearchable v-model="form.group_member_id" label="Member" :options="options.members" :error="errors.group_member_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.loan_product_id" label="Loan product" :options="options.products" :error="errors.loan_product_id?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.requested_amount" type="number" min="0" step="0.01" label="Amount requested" :error="errors.requested_amount?.[0]" /></div>
                    <div v-if="termFor(form.loan_product_id, form.requested_amount) !== undefined" class="col-md-6">
                        <label class="form-label">Term</label>
                        <div class="form-control-plaintext">{{ termText(form.loan_product_id, form.requested_amount) }}</div>
                    </div>
                    <div v-else class="col-md-6"><BaseInput v-model="form.duration_months" type="number" min="1" label="Duration (months)" :error="errors.duration_months?.[0]" /></div>
                    <div class="col-12"><p class="small text-muted mb-1">{{ endText(preview.form) }}</p></div>
                    <div class="col-12"><BaseInput v-model="form.purpose" label="Purpose" :error="errors.purpose?.[0]" /></div>
                </div>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">Submit</button></template>
        </LargeModal>

        <ExtraLargeModal v-if="recordModal" @close="recordModal = false; errors = {}">
            <template v-slot:header><h4>Record an existing loan</h4></template>
            <template v-slot:body>
                <h6>1. Application</h6>
                <div class="row">
                    <div class="col-md-6"><MemberSearchable v-model="record.group_member_id" label="Member" :options="options.members" :error="errors.group_member_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="record.loan_product_id" label="Loan product" :options="options.products" :error="errors.loan_product_id?.[0]" /></div>
                    <div class="col-md-4"><BaseInput v-model="record.requested_amount" type="number" min="0" step="0.01" label="Amount requested" :error="errors.requested_amount?.[0]" /></div>
                    <div class="col-md-4"><BaseInput v-model="record.approved_amount" type="number" min="0" step="0.01" label="Amount approved (blank = same)" :error="errors.approved_amount?.[0]" /></div>
                    <div v-if="termFor(record.loan_product_id, record.approved_amount || record.requested_amount) !== undefined" class="col-md-4">
                        <label class="form-label">Term</label>
                        <div class="form-control-plaintext">{{ termText(record.loan_product_id, record.approved_amount || record.requested_amount) }}</div>
                    </div>
                    <div v-else class="col-md-4"><BaseInput v-model="record.duration_months" type="number" min="1" label="Duration (months)" :error="errors.duration_months?.[0]" /></div>
                    <div class="col-12"><p class="small text-muted mb-1">{{ endText(preview.record) }}</p></div>
                    <div class="col-md-6"><DatePicker v-model="record.application_date" label="Application date" :error="errors.application_date?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="record.disbursement_date" label="Loan given on" :error="errors.disbursement_date?.[0]" /></div>
                    <div class="col-12"><BaseInput v-model="record.purpose" label="Purpose" :error="errors.purpose?.[0]" /></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2">
                    <h6 class="mb-0">2. Guarantors <small class="text-muted">(optional)</small></h6>
                    <button @click="record.guarantors.push({ group_member_id: '', guaranteed_amount: '' })" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg"></i> Guarantor</button>
                </div>
                <div v-for="(guarantor, index) in record.guarantors" :key="index" class="row align-items-end mt-1">
                    <div class="col-md-6"><MemberSearchable v-model="guarantor.group_member_id" label="Guarantor" :options="options.members" :error="errors[`guarantors.${index}.group_member_id`]?.[0]" /></div>
                    <div class="col-md-4"><BaseInput v-model="guarantor.guaranteed_amount" type="number" min="0" step="0.01" label="Amount guaranteed" :error="errors[`guarantors.${index}.guaranteed_amount`]?.[0]" /></div>
                    <div class="col-md-2 mb-2"><button @click="record.guarantors.splice(index, 1)" class="btn btn-outline-danger btn-sm" title="Remove"><i class="bi bi-trash"></i></button></div>
                </div>

                <h6 class="mt-3">3. Loan</h6>
                <p class="small text-muted mb-0">Saving creates the application as approved, marks the guarantors as accepted and creates the loan with its installment schedule from the date above. Eligibility checks for new applications are skipped because this is already recorded history.</p>
            </template>
            <template v-slot:footer><button @click="saveRecord" class="btn btn-primary" :disabled="saving">Record loan</button></template>
        </ExtraLargeModal>

        <MediumModal v-if="decision" @close="decision = null; errors = {}">
            <template v-slot:header><h4>{{ decision.type === "approve" ? "Approve" : "Reject" }} {{ decision.item.application_number }}</h4></template>
            <template v-slot:body>
                <BaseInput v-if="decision.type === 'approve'" v-model="decision.approved_amount" type="number" min="0" step="0.01"
                    :label="`Approved amount (requested ${money(decision.item.requested_amount)})`" :error="errors.approved_amount?.[0]" />
                <BaseInput v-else v-model="decision.rejection_reason" label="Reason for rejecting" :error="errors.rejection_reason?.[0]" />
            </template>
            <template v-slot:footer>
                <button @click="decide" class="btn" :class="decision.type === 'approve' ? 'btn-success' : 'btn-danger'" :disabled="saving">
                    {{ decision.type === "approve" ? "Approve" : "Reject" }}
                </button>
            </template>
        </MediumModal>

        <ImportModal v-if="importModal" title="Import loans" template-url="/loan-applications/import/template" upload-url="/loan-applications/import" :group-id="groupId"
            :fields="importFields" :columns="importColumns"
            note="Every row goes through all the steps at once: application, guarantors (if included), approval and disbursement with the installment schedule. The member is identified by phone number and the product by its code. Eligibility checks for new applications (savings limit, guarantors needed, one loan at a time) are skipped, as when recording an existing loan. A loan with the same member, product, disbursement date and amount is skipped, so the same file can be uploaded again safely. Past repayments are added on the Loan Repayments page. Up to 500 rows per file."
            @close="importModal = false" @imported="load(1)" />
    </div>
</template>

<script>
import debounce from "../../utils/debounce";
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/loan-applications",
            decision: null,
            recordModal: false,
            record: {},
            preview: { form: null, record: null },
            importModal: false,
            importFields: [{ name: "include_guarantors", label: "Include guarantors (adds guarantor columns to the template and reads them)", type: "checkbox", default: false }],
            importColumns: [
                { name: "phone_number", required: true, description: "The borrower's phone number: it identifies the member.", example: "0712345678" },
                { name: "loan_product", required: true, description: "The code of the loan product (see Loan Products).", example: "DHARURA" },
                { name: "amount", required: true, description: "The amount applied for, which is also the amount approved and disbursed.", example: "500000" },
                { name: "application_date", required: true, description: "When the member applied, e.g. 05/01/2026 or 2026-01-05. It cannot be after the disbursement date.", example: "2026-01-05" },
                { name: "disbursement_date", required: true, description: "When the loan was given, in the past or today. The installment schedule starts from this date.", example: "2026-01-10" },
                { name: "purpose", required: true, description: "What the loan is for.", example: "School fees" },
                { name: "duration_months", required: false, description: "The term in months (1 to 600). Leave empty when the product's amount ranges decide the term.", example: "6" },
                { name: "guarantorN_phone / guarantorN_amount", required: false, description: "Only with Include guarantors: up to 3 pairs (guarantor1_phone, guarantor1_amount, ...). A pair needs both cells; guarantors are recorded as accepted.", example: "0754123456 / 300000" },
            ],
        };
    },

    methods: {
        /**
         * The term decided by the product's amount ranges: undefined when the product has no ranges (the applicant chooses), null when no range covers the amount.
         */
        termFor(productId, amount) {
            const product = (this.options.products ?? []).find((option) => String(option.value) === String(productId));

            if (!product || !product.ranges.length) {
                return undefined;
            }

            const value = Number(amount);
            const range = product.ranges.find((item) => value >= item.minimum_amount && (item.maximum_amount === null || value <= item.maximum_amount));

            return range ? range.duration_months : null;
        },

        termText(productId, amount) {
            const months = this.termFor(productId, amount);

            if (!Number(amount)) {
                return "Decided by the amount";
            }

            return months === null ? "No loan range covers this amount" : `${months} ${months === 1 ? "month" : "months"} (decided by the amount)`;
        },

        openRecord() {
            const today = new Date().toISOString().slice(0, 10);

            this.record = {
                group_member_id: "", loan_product_id: "", requested_amount: "", approved_amount: "", duration_months: 6,
                purpose: "", application_date: today, disbursement_date: today, guarantors: [],
            };
            this.errors = {};
            this.recordModal = true;
        },

        saveRecord() {
            this.saving = true;
            this.errors = {};

            axios
                .post(`${this.endpoint}/record`, { ...this.record, approved_amount: this.record.approved_amount === "" ? null : this.record.approved_amount, group_id: this.groupId })
                .then((response) => {
                    this.recordModal = false;
                    this.$toast.success(response.data.message);
                    this.load(1);
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
        },

        cancel(item) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/cancel`));
        },

        openAdd() {
            this.openForm({
                group_member_id: this.options.own_member_id ?? "",
                loan_product_id: "",
                requested_amount: "",
                duration_months: 6,
                purpose: "",
            });
        },

        save() {
            this.submit(axios.post(this.endpoint, { ...this.form, group_id: this.groupId }));
        },

        openDecision(type, item) {
            this.errors = {};
            this.decision = { type, item, approved_amount: item.requested_amount, rejection_reason: "" };
        },

        decide() {
            const { type, item } = this.decision;
            const payload = type === "approve" ? { approved_amount: this.decision.approved_amount } : { rejection_reason: this.decision.rejection_reason };

            this.saving = true;
            this.errors = {};

            axios
                .patch(`${this.endpoint}/${item.id}/${type}`, payload)
                .then((response) => {
                    this.decision = null;
                    this.$toast.success(response.data.message);
                    this.load(this.meta.current_page);
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
        },
    },
};
</script>
