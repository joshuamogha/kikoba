<template>
    <div v-if="shareOut">
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <h4 class="mb-0">
                        {{ shareOut.share_out_number }}
                        <StatusBadge :status="shareOut.status" class="ms-1" />
                    </h4>
                    <div class="small text-muted">
                        {{ shareOut.cycle.name }} &middot; {{ shareOut.cycle.start_date }} to {{ shareOut.cycle.end_date }}
                        &middot; calculated {{ shareOut.calculation_date }}
                        <span v-if="shareOut.approved_at">&middot; approved {{ shareOut.approved_at }}</span>
                    </div>
                </div>
                <a :href="`/groups/${groupId}/share-outs`" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> All share outs
                </a>
                <template v-if="canManage && shareOut.status === 'draft'">
                    <button @click="recalculate" class="btn btn-sm btn-secondary" :disabled="busy">
                        <i class="bi bi-arrow-repeat"></i> Recalculate
                    </button>
                    <button @click="confirm = 'approve'" class="btn btn-sm btn-success" :disabled="busy">
                        <i class="bi bi-check2-circle"></i> Approve
                    </button>
                    <button @click="confirm = 'delete'" class="btn btn-sm btn-danger" :disabled="busy">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </template>
            </div>

            <div class="card-body">
                <div class="row g-3 text-center">
                    <div v-for="card in cards" :key="card.label" class="col-6 col-md-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">{{ card.label }}</div>
                            <div class="fw-semibold fs-5">{{ money(card.value) }}</div>
                        </div>
                    </div>
                </div>
                <div v-if="shareOut.status === 'draft'" class="alert alert-info mt-3 mb-0 py-2">
                    This is a draft. Check the figures, then approve it to lock them and start paying members.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ canManage ? "Members" : "Your share" }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th class="text-end">Savings</th>
                                <th class="text-end">Shares</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Loan owed</th>
                                <th class="text-end">Fines owed</th>
                                <th class="text-end">Net payable</th>
                                <th class="text-end">Paid</th>
                                <th>Status</th>
                                <th v-if="canManage && shareOut.status === 'approved'">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in shareOut.members" :key="row.id">
                                <tr>
                                    <td>
                                        {{ row.member.name }}
                                        <span v-if="row.member.is_self" class="badge text-bg-light border ms-1">You</span>
                                        <div class="small text-muted">{{ row.member.member_number }}</div>
                                    </td>
                                    <td class="text-end">{{ money(row.savings) }}</td>
                                    <td class="text-end">{{ row.shares }} <small class="text-muted">({{ money(row.share_value) }})</small></td>
                                    <td class="text-end">{{ money(row.profit) }}</td>
                                    <td class="text-end">{{ money(row.outstanding_loan) }}</td>
                                    <td class="text-end">{{ money(row.outstanding_fines) }}</td>
                                    <td class="text-end fw-semibold">{{ money(row.net_payable) }}</td>
                                    <td class="text-end">{{ money(row.amount_paid) }}</td>
                                    <td><StatusBadge :status="row.status" /></td>
                                    <td v-if="canManage && shareOut.status === 'approved'">
                                        <button v-if="row.balance > 0" @click="openPayment(row)" class="btn btn-sm btn-primary"
                                            title="Record payment">
                                            <i class="bi bi-cash-coin"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-for="payment in row.payments" :key="payment.id" class="table-light small">
                                    <td colspan="2" class="ps-4 text-muted">
                                        <i class="bi bi-arrow-return-right"></i> {{ payment.payment_number }}
                                    </td>
                                    <td colspan="4">
                                        {{ payment.payment_date }} &middot; {{ payment.method }}
                                        <span v-if="payment.reference" class="text-muted">&middot; {{ payment.reference }}</span>
                                    </td>
                                    <td></td>
                                    <td class="text-end">{{ money(payment.amount) }}</td>
                                    <td :colspan="canManage && shareOut.status === 'approved' ? 2 : 1"></td>
                                </tr>
                            </template>
                            <tr v-if="!shareOut.members.length">
                                <td :colspan="10" class="text-center text-muted py-4">
                                    {{ canManage ? "No members in this share out." : "You are not part of this share out." }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <MediumModal v-if="paying" @close="paying = null">
            <template v-slot:header>
                <h4>Pay {{ paying.member.name }}</h4>
            </template>
            <template v-slot:body>
                <p class="text-muted">Still owed: <strong>{{ money(paying.balance) }}</strong></p>
                <BaseInput v-model="payment.amount" label="Amount" type="number" :error="errors.amount?.[0]" />
                <BaseSelect v-model="payment.payment_method_id" label="Payment method" :options="methodOptions"
                    :error="errors.payment_method_id?.[0]" />
                <DatePicker v-model="payment.payment_date" label="Paid on" max-date="today" :error="errors.payment_date?.[0]" />
                <BaseInput v-model="payment.external_reference" label="Reference (optional)"
                    :error="errors.external_reference?.[0]" />
                <div v-if="errors.member_share_out_id" class="text-danger small">{{ errors.member_share_out_id[0] }}</div>
            </template>
            <template v-slot:footer>
                <button @click="pay" class="btn btn-primary" :disabled="busy">Record payment</button>
            </template>
        </MediumModal>

        <ConfirmModal v-if="confirm" @close="confirm = null">
            <template v-slot:header>
                <h4>{{ confirm === "approve" ? "Approve share out" : "Delete draft" }}</h4>
            </template>
            <template v-slot:body>
                <p v-if="confirm === 'approve'">
                    Once approved the figures are locked and cannot be recalculated. Continue?
                </p>
                <p v-else>The draft will be removed. You can calculate the cycle again afterwards.</p>
            </template>
            <template v-slot:footer>
                <button @click="confirm === 'approve' ? approve() : remove()" class="btn"
                    :class="confirm === 'approve' ? 'btn-success' : 'btn-danger'">
                    {{ confirm === "approve" ? "Approve" : "Delete" }}
                </button>
            </template>
        </ConfirmModal>
    </div>
    <div v-else class="text-center text-muted py-5">Loading...</div>
</template>

<script>
export default {
    props: {
        groupId: { type: Number, required: true },
        shareOutId: { type: Number, required: true },
        canManage: { type: Boolean, default: false },
        paymentMethods: { type: Array, default: () => [] },
    },

    data() {
        return {
            shareOut: null,
            busy: false,
            errors: {},
            confirm: null,
            paying: null,
            payment: { amount: "", payment_method_id: "", payment_date: "", external_reference: "" },
        };
    },

    computed: {
        base() {
            return `/groups/${this.groupId}/share-outs/${this.shareOutId}`;
        },
        methodOptions() {
            return this.paymentMethods.map((method) => ({ value: method.id, label: method.name }));
        },
        cards() {
            const totals = this.shareOut.totals;

            return [
                { label: "Total savings", value: totals.savings },
                { label: "Share value", value: totals.share_value },
                { label: "Profit to share", value: totals.distributable_profit },
                { label: "To distribute", value: totals.distributable },
                { label: "Interest income", value: totals.interest_income },
                { label: "Fine income", value: totals.fine_income },
                { label: "Other income", value: totals.other_income },
                { label: "Expenses", value: totals.expenses },
            ];
        },
    },

    created() {
        this.load();
    },

    methods: {
        load() {
            axios
                .get(this.base)
                .then((response) => (this.shareOut = response.data.data))
                .catch(this.handleError);
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },

        send(request, after) {
            this.busy = true;

            return request
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.confirm = null;
                    this.paying = null;
                    after?.();
                    this.load();
                })
                .catch(this.handleError)
                .finally(() => (this.busy = false));
        },

        recalculate() {
            this.send(axios.put(this.base, { calculation_date: new Date().toISOString().slice(0, 10) }));
        },

        approve() {
            this.send(axios.patch(`${this.base}/approve`));
        },

        remove() {
            this.send(axios.delete(this.base), () => (window.location.href = `/groups/${this.groupId}/share-outs`));
        },

        openPayment(row) {
            this.paying = row;
            this.errors = {};
            this.payment = {
                amount: row.balance,
                payment_method_id: this.methodOptions[0]?.value ?? "",
                payment_date: new Date().toISOString().slice(0, 10),
                external_reference: "",
            };
        },

        pay() {
            this.send(axios.post(`${this.base}/payments`, { ...this.payment, member_share_out_id: this.paying.id }));
        },

        handleError(error) {
            const data = error.response?.data;

            if (error.response?.status === 422 && data?.errors) {
                this.errors = data.errors;

                if (data.errors.status || data.errors.financial_cycle_id) {
                    this.confirm = null;
                    this.$toast.error(data.message);
                }

                return;
            }

            this.$toast.error(data?.message ?? "Something went wrong. Please try again.");
        },
    },
};
</script>
