<template>
    <div v-if="form">
        <div class="row gx-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h4 class="mb-0">Contributions</h4></div>
                    <div class="card-body">
                        <BaseSelect v-model="form.contribution_frequency_id" label="Contribution frequency"
                            :options="form.options.contribution_frequencies" :disabled="!canManage"
                            :error="errors.contribution_frequency_id?.[0]" />
                        <div class="row">
                            <div class="col-md-6">
                                <BaseInput v-model="form.minimum_contribution" type="number" min="0" step="0.01"
                                    :label="`Minimum contribution (${form.currency})`" :disabled="!canManage"
                                    :error="errors.minimum_contribution?.[0]" />
                            </div>
                            <div class="col-md-6">
                                <BaseInput v-model="form.maximum_contribution" type="number" min="0" step="0.01"
                                    :label="`Maximum contribution (${form.currency}, optional)`" :disabled="!canManage"
                                    :error="errors.maximum_contribution?.[0]" />
                            </div>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input id="partial" v-model="form.allow_partial_contributions" class="form-check-input"
                                type="checkbox" :disabled="!canManage" />
                            <label class="form-check-label" for="partial">Allow partial contributions</label>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h4 class="mb-0">Shares</h4></div>
                    <div class="card-body">
                        <BaseInput v-model="form.share_price" type="number" min="0" step="0.01"
                            :label="`Share price (${form.currency})`" :disabled="!canManage"
                            :error="errors.share_price?.[0]" />
                        <div class="row">
                            <div class="col-md-6">
                                <BaseInput v-model="form.minimum_shares" type="number" min="0"
                                    label="Minimum shares per member (per month)" :disabled="!canManage"
                                    :error="errors.minimum_shares?.[0]" />
                            </div>
                            <div class="col-md-6">
                                <BaseInput v-model="form.maximum_shares" type="number" min="0"
                                    label="Maximum shares per month (extra go to the next month)" :disabled="!canManage"
                                    :error="errors.maximum_shares?.[0]" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h4 class="mb-0">Loans</h4></div>
                    <div class="card-body">
                        <BaseSelect v-model="form.loan_interest_method_id" label="Interest method"
                            :options="form.options.loan_interest_methods" :disabled="!canManage"
                            :error="errors.loan_interest_method_id?.[0]" />
                        <BaseSelect v-model="form.default_interest_period" label="How the interest rate is charged" :options="interestPeriods"
                            :disabled="!canManage" :error="errors.default_interest_period?.[0]" />
                        <p class="small text-muted">{{ interestExample(form.default_interest_period, form.default_loan_interest_rate) }}
                            New loan products start with this choice; each product can still be changed.</p>
                        <div class="row">
                            <div class="col-md-6">
                                <BaseInput v-model="form.default_loan_interest_rate" type="number" min="0" step="0.01"
                                    label="Default interest rate (%)" :disabled="!canManage"
                                    :error="errors.default_loan_interest_rate?.[0]" />
                            </div>
                            <div class="col-md-6">
                                <BaseInput v-model="form.maximum_loan_multiplier" type="number" min="0" step="0.1"
                                    label="Maximum loan (× member savings)" :disabled="!canManage"
                                    :error="errors.maximum_loan_multiplier?.[0]" />
                            </div>
                            <div class="col-md-6">
                                <BaseInput v-model="form.default_loan_duration_months" type="number" min="1"
                                    label="Default duration (months)" :disabled="!canManage"
                                    :error="errors.default_loan_duration_months?.[0]" />
                            </div>
                            <div class="col-md-6">
                                <BaseInput v-model="form.loan_grace_period_days" type="number" min="0"
                                    label="Grace period (days)" :disabled="!canManage"
                                    :error="errors.loan_grace_period_days?.[0]" />
                            </div>
                        </div>
                        <BaseInput v-model="form.loan_guarantors_required" type="number" min="0"
                            label="Guarantors required" :disabled="!canManage"
                            :error="errors.loan_guarantors_required?.[0]" />
                        <div class="form-check form-switch mt-2">
                            <input id="multiple" v-model="form.allow_multiple_loans" class="form-check-input"
                                type="checkbox" :disabled="!canManage" />
                            <label class="form-check-label" for="multiple">Allow a member to hold multiple loans</label>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h4 class="mb-0">Penalties and approvals</h4></div>
                    <div class="card-body">
                        <BaseSelect v-model="form.penalty_type_id" label="Default penalty type"
                            :options="form.options.penalty_types" :disabled="!canManage"
                            :error="errors.penalty_type_id?.[0]" />
                        <BaseInput v-model="form.late_payment_penalty_value" type="number" min="0" step="0.01"
                            :label="`Late payment penalty (${form.currency})`" :disabled="!canManage"
                            :error="errors.late_payment_penalty_value?.[0]" />
                        <BaseInput v-model="form.withdrawal_approvals_required" type="number" min="0"
                            label="Approvals required for withdrawals" :disabled="!canManage"
                            :error="errors.withdrawal_approvals_required?.[0]" />
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h4 class="mb-0">Opening balance</h4></div>
                    <div class="card-body">
                        <p class="small text-muted">The money the group held (for example in its M-Pesa account and cash) on the day it started using Kibubu. The Cash Reconciliation report adds it to everything recorded from that day on.</p>
                        <BaseInput v-model="form.opening_balance" type="number" min="0" step="0.01"
                            :label="`Opening balance (${form.currency})`" :disabled="!canManage"
                            :error="errors.opening_balance?.[0]" />
                        <BaseInput v-model="form.opening_balance_date" type="date"
                            label="Balance as of (date)" :disabled="!canManage"
                            :error="errors.opening_balance_date?.[0]" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="canManage" class="text-end mb-3">
            <button @click="save" class="btn btn-primary" :disabled="saving">Save settings</button>
        </div>
        <p v-else class="small text-muted">Only the group's leaders can change these settings.</p>
    </div>
</template>

<script>
import { interestExample, interestPeriods } from "../../utils/interestExample";

const booleans = ["allow_partial_contributions", "allow_multiple_loans"];

export default {
    props: {
        groupId: { type: Number, required: true },
        canManage: { type: Boolean, default: false },
    },

    data() {
        return { form: null, errors: {}, saving: false, interestPeriods };
    },

    computed: {
        url() {
            return `/groups/${this.groupId}/settings`;
        },
    },

    created() {
        axios
            .get(this.url)
            .then((response) => (this.form = response.data.data))
            .catch(this.handleError);
    },

    methods: {
        interestExample,

        save() {
            this.saving = true;
            this.errors = {};

            const { options, currency, id, ...payload } = this.form;
            booleans.forEach((key) => (payload[key] = Boolean(payload[key])));

            axios
                .put(this.url, payload)
                .then((response) => {
                    this.form = { ...this.form, ...response.data.data };
                    this.$toast.success(response.data.message);
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
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
