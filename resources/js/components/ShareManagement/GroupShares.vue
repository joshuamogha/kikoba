<template>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Shares</h4>
                <div v-if="canManage" class="btn-group btn-group-sm">
                    <button @click="openAddModal" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add shares</button>
                    <button @click="importModal = true" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Import</button>
                </div>
            </div>
        </div>
        <div class="card-body border-bottom">
            <div class="row text-center">
                <div class="col-6">
                    <div class="text-muted small">Total shares</div>
                    <div class="fs-4 fw-semibold">{{ totals.shares.toLocaleString() }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Total amount of shares</div>
                    <div class="fs-4 fw-semibold">{{ money(totals.amount) }}</div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Member</th>
                        <th class="text-end">Shares</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                        <th>Month</th>
                        <th>Date paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.transaction_number }}</td>
                        <td>
                            {{ item.member.name }}
                            <div class="small text-muted">{{ item.member.member_number }}</div>
                        </td>
                        <td class="text-end">{{ item.shares }}</td>
                        <td class="text-end">{{ money(item.price_per_share) }}</td>
                        <td class="text-end">{{ money(item.total_amount) }}</td>
                        <td>{{ item.period }}</td>
                        <td>{{ item.transaction_date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                    </tr>
                    <tr v-if="!items.length">
                        <td colspan="9" class="text-center text-muted py-4">No shares recorded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer">
            <Pagination :meta="meta" @change="load" />
        </div>

        <ImportModal v-if="importModal" title="Import member shares" :template-url="`${base}/import/template`" :upload-url="`${base}/import`" :group-id="groupId"
            :columns="importColumns" note="The member is identified by phone number and must already be an active member of this group. Each row is recorded as an approved purchase: shares = amount ÷ the group's current share price, in the month of the transaction date. A member can buy at most the group's maximum shares in a month; the rest of a row moves to the next month. Up to 500 rows per file."
            @close="importModal = false" @imported="load(1)" />

        <MediumModal v-if="formModal" @close="closeModal">
            <template v-slot:header>
                <h4>Add shares</h4>
            </template>
            <template v-slot:body>
                <MemberSearchable v-model="form.group_member_id" label="Member" :options="members"
                    :error="errors.group_member_id?.[0]" />
                <BaseInput v-model="form.number_of_shares" type="number" min="1" label="Number of shares"
                    :error="errors.number_of_shares?.[0]" />
                <p class="small text-muted mb-2">
                    Price per share: {{ money(defaultPrice) }} (set in group settings).
                    Each member buys {{ minShares }}{{ maxShares === null ? " or more" : ` to ${maxShares}` }} shares per month. Shares above the maximum move to the next month.
                </p>
                <DatePicker v-model="form.transaction_date" label="Date paid"
                    :error="errors.transaction_date?.[0]" />
                <MonthPicker v-model="form.period" label="For month" :error="errors.period?.[0]" />
                <BaseInput v-model="form.reference" label="Reference (optional)" :error="errors.reference?.[0]" />
                <p class="small text-muted mb-0">Total: {{ money(total) }}</p>
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary" :disabled="saving">Save</button>
            </template>
        </MediumModal>
    </div>
</template>

<script>
export default {
    props: {
        groupId: { type: Number, required: true },
        canManage: { type: Boolean, default: false },
    },

    data() {
        return {
            items: [],
            totals: { shares: 0, amount: 0 },
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            members: [],
            defaultPrice: 0,
            minShares: 0,
            maxShares: null,
            formModal: false,
            importModal: false,
            importColumns: [
                { name: "phone_number", required: true, description: "The member's phone number: it identifies the member. Spaces and dashes are ignored.", example: "0712345678" },
                { name: "amount", required: true, description: "The total amount the member paid. It must be a multiple of the share price: the number of shares is the amount divided by the price.", example: "20000" },
                { name: "transaction_date", required: true, description: "The date the member paid, e.g. 15/12/2025 08:32:47 or 2025-12-15 (the time is optional). Its month is the period the shares belong to.", example: "2026-02-05" },
                { name: "receipt", required: false, description: "The receipt number of the payment. It is unique: a receipt that is already recorded in this group is skipped, so the same file can be uploaded again safely. Without a receipt, a row with the same member, date and amount as an earlier one is treated as a duplicate.", example: "RCT-0001" },
            ],
            saving: false,
            errors: {},
            form: {},
        };
    },

    computed: {
        base() {
            return `/groups/${this.groupId}/shares`;
        },
        total() {
            return (Number(this.form.number_of_shares) || 0) * this.defaultPrice;
        },
    },

    created() {
        this.load();
    },

    methods: {
        load(page = 1) {
            axios
                .get(this.base, { params: { page } })
                .then((response) => {
                    this.items = response.data.data;
                    this.meta = response.data.meta;
                    this.totals = response.data.totals ?? this.totals;
                    this.members = response.data.meta_options?.members ?? [];
                    this.defaultPrice = response.data.meta_options?.price_per_share ?? 0;
                    this.minShares = response.data.meta_options?.minimum_shares ?? 0;
                    this.maxShares = response.data.meta_options?.maximum_shares ?? null;
                })
                .catch(this.handleError);
        },

        openAddModal() {
            this.form = {
                group_member_id: "",
                number_of_shares: 1,
                transaction_date: new Date().toISOString().slice(0, 10),
                period: new Date().toISOString().slice(0, 7),
                reference: "",
            };
            this.errors = {};
            this.formModal = true;
        },

        save() {
            this.saving = true;
            this.errors = {};

            axios
                .post(this.base, this.form)
                .then((response) => {
                    this.closeModal();
                    this.$toast.success(response.data.message);
                    this.load();
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
        },

        closeModal() {
            this.formModal = false;
            this.errors = {};
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },

        handleError(error) {
            const data = error.response?.data;

            if (error.response?.status === 422 && data?.errors) {
                this.errors = data.errors;
                return;
            }

            this.$toast.error(data?.error ?? data?.message ?? "Something went wrong. Please try again.");
        },
    },
};
</script>
