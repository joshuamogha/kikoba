<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Member fines" :groups="groups" :group-id="groupId" :statuses="['pending', 'partial', 'paid', 'waived']"
                :can-manage="canManage" add-label="Fine" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div v-if="canManage" class="card-body py-2 border-bottom d-flex justify-content-between align-items-center">
            <span class="small text-muted">Have fines on paper or in a spreadsheet? Import them in one file.</span>
            <button @click="importModal = true" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Import</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Member</th><th>Fine</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Date</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.fine_number }}</td>
                        <td>{{ item.member.name }}<div class="small text-muted">{{ item.member.member_number }}</div></td>
                        <td>{{ item.fine_type }}<div class="small text-muted">{{ item.reason }}</div></td>
                        <td class="text-end">{{ money(item.amount) }}<div class="small text-muted">Paid {{ money(item.amount_paid) }}</div></td>
                        <td class="text-end">{{ money(item.balance) }}</td>
                        <td>{{ item.fine_date }}<div v-if="item.due_date" class="small text-muted">Due {{ item.due_date }}</div></td>
                        <td><StatusBadge :status="item.status" /><div v-if="item.waiver_reason" class="small text-muted">{{ item.waiver_reason }}</div></td>
                        <td v-if="canManage">
                            <div v-if="item.status === 'pending' || item.status === 'partial'" class="btn-group btn-group-sm">
                                <button @click="openPay(item)" class="btn btn-success" title="Record payment"><i class="bi bi-cash"></i></button>
                                <button @click="openWaive(item)" class="btn btn-secondary" title="Waive"><i class="bi bi-slash-circle"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 9 : 8" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>Issue fine</h4></template>
            <template v-slot:body>
                <div v-if="!options.fine_types?.length" class="alert alert-warning py-2">No active fine types yet. Add one on the Fine types tab first.</div>
                <div class="row">
                    <div class="col-md-6"><MemberSearchable v-model="form.group_member_id" label="Member" :options="options.members" :error="errors.group_member_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.fine_type_id" label="Fine type" :options="options.fine_types" :error="errors.fine_type_id?.[0]" @update:modelValue="useDefaultAmount" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.amount" type="number" min="0" step="0.01" label="Amount" :error="errors.amount?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.fine_date" label="Fine date" :error="errors.fine_date?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.due_date" label="Due date (optional)" :error="errors.due_date?.[0]" /></div>
                    <div class="col-12"><BaseInput v-model="form.reason" label="Reason" :error="errors.reason?.[0]" /></div>
                </div>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">Issue</button></template>
        </LargeModal>

        <MediumModal v-if="payModal" @close="payModal = false; errors = {}">
            <template v-slot:header><h4>Record payment for {{ form.fine_number }} (balance {{ money(form.balance) }})</h4></template>
            <template v-slot:body>
                <BaseInput v-model="form.pay_amount" type="number" min="0" step="0.01" label="Amount" :error="errors.amount?.[0]" />
                <BaseSelect v-model="form.payment_method_id" label="Payment method" :options="options.payment_methods" :error="errors.payment_method_id?.[0]" />
                <DatePicker v-model="form.payment_date" label="Date" :error="errors.payment_date?.[0]" />
                <BaseInput v-model="form.external_reference" label="Reference (optional)" :error="errors.external_reference?.[0]" />
            </template>
            <template v-slot:footer><button @click="pay" class="btn btn-success" :disabled="saving">Save payment</button></template>
        </MediumModal>

        <MediumModal v-if="waiveModal" @close="waiveModal = false; errors = {}">
            <template v-slot:header><h4>Waive {{ form.fine_number }}</h4></template>
            <template v-slot:body>
                <BaseInput v-model="form.waiver_reason" label="Reason for waiving" :error="errors.waiver_reason?.[0]" />
            </template>
            <template v-slot:footer><button @click="waive" class="btn btn-danger" :disabled="saving">Waive fine</button></template>
        </MediumModal>

        <ImportModal v-if="importModal" title="Import member fines" template-url="/member-fines/import/template" upload-url="/member-fines/import" :group-id="groupId"
            :fields="[]" :columns="importColumns"
            note="Every row is issued as a pending fine in the month of its fine date. The member is identified by phone number and must be an active member; the fine type is matched by name. Without an amount, the fine type's default amount is used. A row identical to an existing fine (same member, type, date, amount and reason) is skipped, so the same file can be uploaded again safely. Up to 500 rows per file."
            @close="importModal = false" @imported="load(1)" />
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/member-fines", payModal: false, waiveModal: false, importModal: false,
            importColumns: [
                { name: "phone_number", required: true, description: "The member's phone number: it identifies the member. Spaces and dashes are ignored.", example: "0712345678" },
                { name: "fine_type", required: true, description: "The name of an active fine type of this group.", example: "Absent" },
                { name: "fine_date", required: true, description: "The date of the fine, e.g. 15/12/2025 or 2025-12-15. It cannot be in the future.", example: "2026-02-05" },
                { name: "reason", required: true, description: "Why the member is fined.", example: "Missed the monthly meeting" },
                { name: "amount", required: false, description: "The fine amount. Leave empty to use the fine type's default amount.", example: "2000" },
                { name: "due_date", required: false, description: "When the fine is due. It cannot be before the fine date.", example: "2026-03-05" },
            ],
        };
    },

    methods: {
        today() {
            return new Date().toISOString().slice(0, 10);
        },

        openAdd() {
            this.openForm({ group_member_id: "", fine_type_id: "", amount: "", fine_date: this.today(), due_date: "", reason: "" });
        },

        useDefaultAmount(typeId) {
            const type = (this.options.fine_types ?? []).find((option) => String(option.value) === String(typeId));

            if (type && type.calculation_type === "fixed") {
                this.form.amount = type.default_amount;
            }
        },

        save() {
            this.submit(axios.post(this.endpoint, { ...this.form, group_id: this.groupId }));
        },

        openPay(item) {
            this.form = { ...item, pay_amount: item.balance, payment_method_id: "", payment_date: this.today(), external_reference: "" };
            this.errors = {};
            this.payModal = true;
        },

        openWaive(item) {
            this.form = { ...item, waiver_reason: "" };
            this.errors = {};
            this.waiveModal = true;
        },

        pay() {
            this.finish(axios.post(`${this.endpoint}/${this.form.id}/payments`, {
                amount: this.form.pay_amount, payment_method_id: this.form.payment_method_id, payment_date: this.form.payment_date, external_reference: this.form.external_reference,
            }));
        },

        waive() {
            this.finish(axios.patch(`${this.endpoint}/${this.form.id}/waive`, { waiver_reason: this.form.waiver_reason }));
        },

        finish(request) {
            this.saving = true;
            this.errors = {};

            request
                .then((response) => {
                    this.payModal = false;
                    this.waiveModal = false;
                    this.$toast.success(response.data.message);
                    this.load(this.meta.current_page);
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
        },
    },
};
</script>
