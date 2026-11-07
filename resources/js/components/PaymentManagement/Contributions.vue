<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Contributions" :groups="groups" :group-id="groupId" :statuses="['pending', 'approved']"
                :can-manage="canManage" add-label="Contribution" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div v-if="canManage" class="card-body py-2 border-bottom d-flex justify-content-between align-items-center">
            <span class="small text-muted">Have payments on paper or in a spreadsheet? Import them for one fund at a time.</span>
            <button @click="importModal = true" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Import</button>
        </div>
        <div class="card-body border-bottom pb-1">
            <ListFilters :model-value="filters" :members="filterOptions.members" :cycles="filterOptions.cycles" :funds="filterOptions.funds" @update:modelValue="onFilters" />
        </div>
        <div v-if="totals" class="card-body border-bottom">
            <div class="row text-center">
                <div class="col-6">
                    <div class="text-muted small">Total contributions</div>
                    <div class="fs-4 fw-semibold">{{ totals.count.toLocaleString() }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Total amount{{ filters.group_fund_id ? " (chosen fund)" : "" }}</div>
                    <div class="fs-4 fw-semibold">{{ money(totals.amount) }}</div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Member</th><th>Funds</th><th class="text-end">Total</th><th>Month</th><th>Date paid</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.contribution_number }}</td>
                        <td>{{ item.member.name }}<div class="small text-muted">{{ item.member.member_number }}</div></td>
                        <td class="small"><div v-for="fund in item.funds" :key="fund.name">{{ fund.name }}: {{ money(fund.amount) }}</div></td>
                        <td class="text-end">{{ money(item.total_amount) }}<div class="small text-muted">{{ item.payment_method }}</div></td>
                        <td>{{ item.period }}</td>
                        <td>{{ item.contribution_date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button @click="showDetails(item)" class="btn btn-info" title="View details"><i class="bi bi-eye"></i></button>
                                <template v-if="canManage">
                                    <button v-if="item.status === 'pending'" @click="approve(item)" class="btn btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    <button v-if="item.editable" @click="openEdit(item)" class="btn btn-secondary" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button v-if="item.editable" @click="deleting = item" class="btn btn-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    <span v-if="item.share_linked" class="badge text-bg-light border align-self-center" title="Recorded from a share purchase: change it on the Shares page">Share</span>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="9" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit contribution" : "Add contribution" }}</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><MemberSearchable v-model="form.group_member_id" label="Member" :options="memberChoices" :error="errors.group_member_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.payment_method_id" label="Payment method" :options="methodChoices" :error="errors.payment_method_id?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.contribution_date" label="Date paid" :error="errors.contribution_date?.[0]" /></div>
                    <div class="col-md-6"><MonthPicker v-model="form.period" label="For month" :error="errors.period?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.external_reference" label="Reference (optional)" :error="errors.external_reference?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.notes" label="Notes (optional)" :error="errors.notes?.[0]" /></div>
                </div>
                <label class="form-label mt-2">Amount per fund (fill in at least one)</label>
                <div v-if="!fundChoices.length" class="alert alert-warning py-2">
                    This group has no active funds yet. <a href="/group-funds">Add a fund</a> first, then come back.
                </div>
                <div class="row">
                    <div v-for="fund in fundChoices" :key="fund.value" class="col-md-6">
                        <div class="input-group mb-2">
                            <span class="input-group-text w-50 text-truncate">{{ fund.label }}</span>
                            <input v-model="form.amounts[fund.value]" type="number" min="0" step="0.01" class="form-control" />
                        </div>
                    </div>
                </div>
                <div v-if="itemsError" class="text-danger small">{{ itemsError }}</div>
                <p class="small text-muted mb-0">Total: {{ money(total) }}</p>
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ? "Update" : "Save" }}</button>
            </template>
        </LargeModal>

        <ImportModal v-if="importModal" title="Import contributions" template-url="/contributions/import/template" upload-url="/contributions/import" :group-id="groupId"
            :fields="importFields" :columns="importColumns"
            note="Choose the fund first: every row is recorded as an approved contribution to that fund, in the month of its transaction date. The member is identified by phone number and must be an active member. A receipt that is already recorded is skipped, so the same file can be uploaded again safely. Shares are imported on the Shares page. Up to 500 rows per file."
            @close="importModal = false" @imported="load(1)" />

        <LargeModal v-if="detail" @close="detail = null">
            <template v-slot:header><h4>Contribution {{ detail.contribution_number }}</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><strong>Member:</strong> {{ detail.member.name }} ({{ detail.member.member_number }})</div>
                    <div class="col-md-6"><strong>Status:</strong> <StatusBadge :status="detail.status" /></div>
                    <div class="col-md-6"><strong>Date paid:</strong> {{ detail.contribution_date }}</div>
                    <div class="col-md-6"><strong>For month:</strong> {{ detail.period }}</div>
                    <div class="col-md-6"><strong>Financial cycle:</strong> {{ detail.cycle ?? "-" }}</div>
                    <div class="col-md-6"><strong>Payment method:</strong> {{ detail.payment_method }}</div>
                    <div class="col-md-6"><strong>Reference:</strong> {{ detail.external_reference ?? "-" }}</div>
                    <div class="col-md-6"><strong>Payment transaction:</strong> {{ detail.payment_transaction ?? "-" }}</div>
                    <div class="col-md-6"><strong>Received by:</strong> {{ detail.received_by ?? "-" }}</div>
                    <div class="col-md-6"><strong>Approved by:</strong> {{ detail.approved_by ?? "-" }}<span v-if="detail.approved_at"> ({{ detail.approved_at }})</span></div>
                    <div class="col-md-12"><strong>Notes:</strong> {{ detail.notes ?? "-" }}</div>
                </div>
                <div v-if="detail.share_linked" class="alert alert-info py-2 mt-2 mb-0">
                    Records a share purchase ({{ detail.share_purchases.map((purchase) => `${purchase.number}: ${purchase.shares} shares`).join(", ") }}). Change it on the Shares page.
                </div>
                <table class="table table-sm table-bordered mt-3 mb-0">
                    <thead><tr><th>Fund</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr v-for="fund in detail.funds" :key="fund.id"><td>{{ fund.name }}</td><td class="text-end">{{ money(fund.amount) }}</td></tr>
                        <tr class="fw-semibold"><td>Total</td><td class="text-end">{{ money(detail.total_amount) }}</td></tr>
                    </tbody>
                </table>
            </template>
        </LargeModal>

        <ConfirmModal v-if="deleting" @close="deleting = null">
            <template v-slot:header><h4>Alert</h4></template>
            <template v-slot:body>
                Delete contribution {{ deleting.contribution_number }} ({{ money(deleting.total_amount) }} from {{ deleting.member.name }})?
                <span v-if="deleting.status === 'approved'"> It is approved, so its payment transaction is removed too.</span>
            </template>
            <template v-slot:footer><button @click="destroy" class="btn btn-danger">Delete</button></template>
        </ConfirmModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/contributions",
            detail: null,
            deleting: null,
            importModal: false,
            importColumns: [
                { name: "phone_number", required: true, description: "The member's phone number: it identifies the member. Spaces and dashes are ignored.", example: "0712345678" },
                { name: "amount", required: true, description: "The amount the member paid to the chosen fund.", example: "20000" },
                { name: "transaction_date", required: true, description: "The date the member paid, e.g. 15/12/2025 08:32:47 or 2025-12-15 (the time is optional). Its month is the month the payment is for.", example: "2026-02-05" },
                { name: "receipt", required: false, description: "The receipt number. It is unique: a receipt already recorded in this group is skipped. Without one, the same member, date, fund and amount counts as a duplicate.", example: "RCT-0001" },
            ],
        };
    },

    computed: {
        importFields() {
            return [
                { name: "group_fund_id", label: "Fund", type: "select", required: true, placeholder: "Choose the fund...", options: this.options.funds ?? [] },
                { name: "payment_method_id", label: "Payment method (default: Cash)", type: "select", placeholder: "Cash / the group's first method", options: this.options.payment_methods ?? [] },
            ];
        },
        memberChoices() {
            const current = this.form.current_member;

            return current && !(this.options.members ?? []).some((member) => member.value === current.value) ? [current, ...this.options.members] : (this.options.members ?? []);
        },
        methodChoices() {
            const current = this.form.current_method;

            return current && !(this.options.payment_methods ?? []).some((method) => method.value === current.value) ? [current, ...this.options.payment_methods] : (this.options.payment_methods ?? []);
        },
        fundChoices() {
            const extra = (this.form.current_funds ?? []).filter((fund) => !(this.options.funds ?? []).some((option) => option.value === fund.value));

            return [...(this.options.funds ?? []), ...extra];
        },
        total() {
            return Object.values(this.form.amounts ?? {}).reduce((sum, amount) => sum + (Number(amount) || 0), 0);
        },
        itemsError() {
            const key = Object.keys(this.errors).find((name) => name === "items" || name.startsWith("items."));

            return key ? "Enter an amount for at least one fund." : "";
        },
    },

    methods: {
        openAdd() {
            this.openForm({
                group_member_id: "",
                payment_method_id: "",
                contribution_date: new Date().toISOString().slice(0, 10),
                period: new Date().toISOString().slice(0, 7),
                external_reference: "",
                amounts: {},
            });
        },

        openEdit(item) {
            this.openForm({
                id: item.id,
                group_member_id: item.member.id,
                payment_method_id: item.payment_method_id,
                contribution_date: item.contribution_date,
                period: item.period,
                external_reference: item.external_reference ?? "",
                notes: item.notes ?? "",
                amounts: Object.fromEntries(item.funds.map((fund) => [fund.id, fund.amount])),
                current_member: { value: item.member.id, label: `${item.member.name} (${item.member.member_number})` },
                current_method: { value: item.payment_method_id, label: item.payment_method },
                current_funds: item.funds.map((fund) => ({ value: fund.id, label: fund.name })),
            });
        },

        showDetails(item) {
            axios
                .get(`${this.endpoint}/${item.id}`)
                .then((response) => (this.detail = response.data.data))
                .catch(this.handleError);
        },

        destroy() {
            const item = this.deleting;

            this.deleting = null;
            this.act(axios.delete(`${this.endpoint}/${item.id}`, { data: { group_id: this.groupId } }));
        },

        save() {
            const items = Object.entries(this.form.amounts)
                .filter(([, amount]) => Number(amount) > 0)
                .map(([fundId, amount]) => ({ group_fund_id: Number(fundId), amount }));
            const { amounts, current_member, current_method, current_funds, id, ...rest } = this.form;
            const payload = { ...rest, items, group_id: this.groupId };

            this.submit(id ? axios.put(`${this.endpoint}/${id}`, payload) : axios.post(this.endpoint, payload));
        },

        approve(item) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/approve`));
        },
    },
};
</script>
