<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan products" :groups="groups" :group-id="groupId" :statuses="['active', 'inactive']"
                :can-manage="canManage" add-label="Product" @group="onGroup" @search="onSearch" @status="onStatus" @add="openForm(blank())" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Product</th><th>Fund</th><th class="text-end">Amount</th><th>Interest</th><th>Duration</th><th>Guarantors</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td><strong>{{ item.name }}</strong><div class="small text-muted">{{ item.code }}</div></td>
                        <td>{{ item.fund }}<div class="small text-muted">{{ item.repayment_frequency }}</div></td>
                        <td class="text-end">{{ money(item.minimum_amount) }} - {{ item.maximum_amount === null ? "no limit" : money(item.maximum_amount) }}</td>
                        <td>{{ item.interest_rate }}% {{ periodLabel(item.interest_period) }}<div class="small text-muted">{{ item.interest_method }}</div></td>
                        <td>
                            <template v-if="item.ranges.length">
                                <div v-for="range in item.ranges" :key="range.minimum_amount" class="small">
                                    {{ money(range.minimum_amount) }} - {{ range.maximum_amount === null ? "no limit" : money(range.maximum_amount) }}: {{ range.duration_months }} {{ range.duration_months === 1 ? "month" : "months" }}
                                </div>
                            </template>
                            <template v-else>{{ item.minimum_duration_months }} - {{ item.maximum_duration_months }} months</template>
                            <div class="small text-muted">{{ item.repayment_mode === "lump_sum" ? "One payment at the end" : "Installments" }}</div>
                        </td>
                        <td>{{ item.guarantors_required }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td v-if="canManage">
                            <div class="btn-group btn-group-sm">
                                <button @click="toggle(item)" class="btn" :class="item.status === 'active' ? 'btn-warning' : 'btn-success'" :title="item.status === 'active' ? 'Deactivate' : 'Activate'">
                                    <i class="bi" :class="item.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                                </button>
                                <button @click="openForm(editable(item))" class="btn btn-secondary" title="Edit"><i class="bi bi-pencil"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 9 : 8" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit loan product" : "Add loan product" }}</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseInput v-model="form.name" label="Name" :error="errors.name?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.code" label="Code" :error="errors.code?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.group_fund_id" label="Loan fund" :options="options.funds" :error="errors.group_fund_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.repayment_frequency_id" label="Repayment frequency" :options="options.repayment_frequencies" :error="errors.repayment_frequency_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.loan_interest_method_id" label="Interest method" :options="options.interest_methods" :error="errors.loan_interest_method_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.interest_period" label="Rate is charged" :options="periods" :error="errors.interest_period?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.interest_rate" type="number" min="0" step="0.01" label="Interest rate (%)" :error="errors.interest_rate?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.maximum_savings_multiplier" type="number" min="0" step="0.1" label="Maximum loan (× member savings, 0 = no cap)" :error="errors.maximum_savings_multiplier?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.minimum_amount" type="number" min="0" step="0.01" label="Minimum amount" :error="errors.minimum_amount?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.maximum_amount" type="number" min="0" step="0.01" label="Maximum amount (optional)" :error="errors.maximum_amount?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.repayment_mode" label="Repayment" :options="modes" :error="errors.repayment_mode?.[0]" /></div>
                    <template v-if="!form.ranges?.length">
                        <div class="col-md-6"><BaseInput v-model="form.minimum_duration_months" type="number" min="1" label="Minimum duration (months)" :error="errors.minimum_duration_months?.[0]" /></div>
                        <div class="col-md-6"><BaseInput v-model="form.maximum_duration_months" type="number" min="1" label="Maximum duration (months)" :error="errors.maximum_duration_months?.[0]" /></div>
                    </template>
                    <div class="col-md-6"><BaseInput v-model="form.grace_period_days" type="number" min="0" label="Grace period (days)" :error="errors.grace_period_days?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.guarantors_required" type="number" min="0" label="Guarantors required" :error="errors.guarantors_required?.[0]" /></div>
                    <div class="col-12"><BaseInput v-model="form.description" label="Description (optional)" :error="errors.description?.[0]" /></div>
                </div>

                <p class="small text-muted mt-2 mb-0">
                    To make the amount decide the term (for example 0 to 100,000 = 1 month), add amount ranges for this product on the
                    <a href="/loan-ranges">Loan Ranges</a> page.
                </p>
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ? "Update" : "Save" }}</button>
            </template>
        </LargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/loan-products",
            modes: [
                { value: "installments", label: "Installments by repayment frequency" },
                { value: "lump_sum", label: "One payment at the end of the term" },
            ],
            periods: [
                { value: "per_month", label: "Per month" },
                { value: "per_year", label: "Per year" },
                { value: "per_loan", label: "Once for the whole loan" },
            ],
        };
    },

    methods: {
        periodLabel(period) {
            return { per_month: "per month", per_year: "per year", per_loan: "per loan" }[period] ?? period;
        },

        editable(item) {
            return {
                ...item,
                description: item.description ?? "",
                maximum_amount: item.maximum_amount ?? "",
                ranges: item.ranges,
            };
        },

        blank() {
            const defaults = this.options.defaults ?? {};

            return {
                id: null, name: "", code: "", description: "", group_fund_id: "", repayment_frequency_id: "", loan_interest_method_id: "",
                interest_period: "per_month", interest_rate: defaults.interest_rate ?? 0, maximum_savings_multiplier: defaults.maximum_savings_multiplier ?? 3,
                minimum_amount: 0, maximum_amount: "", minimum_duration_months: 1, maximum_duration_months: defaults.maximum_duration_months ?? 12,
                grace_period_days: defaults.grace_period_days ?? 0, guarantors_required: defaults.guarantors_required ?? 0, status: "active",
                repayment_mode: "installments", ranges: [],
            };
        },

        payload(extra = {}) {
            return {
                ...this.form,
                ...extra,
                group_id: this.groupId,
                maximum_amount: this.form.maximum_amount === "" ? null : this.form.maximum_amount,
            };
        },

        save() {
            this.submit(this.form.id ? axios.put(`${this.endpoint}/${this.form.id}`, this.payload()) : axios.post(this.endpoint, this.payload()));
        },

        toggle(item) {
            this.form = this.editable(item);
            this.act(axios.put(`${this.endpoint}/${item.id}`, this.payload({ status: item.status === "active" ? "inactive" : "active" })));
        },
    },
};
</script>
