<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Contributions" :groups="groups" :group-id="groupId" :statuses="['pending', 'approved']"
                :can-manage="canManage" add-label="Contribution" @group="onGroup" @search="onSearch" @status="onStatus" @add="openAdd" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Member</th><th>Funds</th><th class="text-end">Total</th><th>Month</th><th>Date paid</th><th>Status</th><th v-if="canManage">Action</th></tr>
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
            <template v-slot:header><h4>Add contribution</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><MemberSearchable v-model="form.group_member_id" label="Member" :options="options.members" :error="errors.group_member_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.payment_method_id" label="Payment method" :options="options.payment_methods" :error="errors.payment_method_id?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.contribution_date" label="Date paid" :error="errors.contribution_date?.[0]" /></div>
                    <div class="col-md-6"><MonthPicker v-model="form.period" label="For month" :error="errors.period?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.external_reference" label="Reference (optional)" :error="errors.external_reference?.[0]" /></div>
                </div>
                <label class="form-label mt-2">Amount per fund (fill in at least one)</label>
                <div v-if="!options.funds?.length" class="alert alert-warning py-2">
                    This group has no active funds yet. <a href="/group-funds">Add a fund</a> first, then come back.
                </div>
                <div class="row">
                    <div v-for="fund in options.funds" :key="fund.value" class="col-md-6">
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
        return { endpoint: "/contributions" };
    },

    computed: {
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

        save() {
            const items = Object.entries(this.form.amounts)
                .filter(([, amount]) => Number(amount) > 0)
                .map(([fundId, amount]) => ({ group_fund_id: Number(fundId), amount }));
            const { amounts, ...rest } = this.form;

            this.submit(axios.post(this.endpoint, { ...rest, items, group_id: this.groupId }));
        },

        approve(item) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/approve`));
        },
    },
};
</script>
