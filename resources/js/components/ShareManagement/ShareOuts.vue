<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-8">
                    <h4 class="mb-0">Share outs</h4>
                </div>
                <div class="col-md-4 text-md-end">
                    <button v-if="canManage" @click="openModal" class="btn btn-primary">
                        <i class="bi bi-calculator"></i> Calculate share out
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Number</th>
                            <th>Cycle</th>
                            <th>Calculated on</th>
                            <th class="text-end">To distribute</th>
                            <th class="text-end">Paid</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(shareOut, index) in shareOuts" :key="shareOut.id">
                            <td>{{ (meta.from || 1) + index }}</td>
                            <td>{{ shareOut.share_out_number }}</td>
                            <td>{{ shareOut.cycle.name }}</td>
                            <td>{{ shareOut.calculation_date }}</td>
                            <td class="text-end">{{ money(shareOut.totals.distributable) }}</td>
                            <td class="text-end">{{ money(shareOut.amount_paid) }}</td>
                            <td><StatusBadge :status="shareOut.status" /></td>
                            <td>
                                <a :href="shareOut.url" class="btn btn-sm btn-primary" title="Open">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <tr v-if="!shareOuts.length">
                            <td colspan="8" class="text-center text-muted py-4">{{ emptyMessage }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="meta.total" class="card-footer">
            <Pagination :meta="meta" @change="getShareOuts" />
        </div>

        <MediumModal v-if="modal" @close="modal = false">
            <template v-slot:header>
                <h4>Calculate share out</h4>
            </template>
            <template v-slot:body>
                <p v-if="!cycles.length" class="text-muted">
                    There is no closed cycle waiting for a share out. A cycle must be closed before its savings can be shared out.
                </p>
                <template v-else>
                    <BaseSelect v-model="form.financial_cycle_id" label="Cycle" :options="cycles"
                        :error="errors.financial_cycle_id?.[0]" />
                    <DatePicker v-model="form.calculation_date" label="Calculation date" max-date="today"
                        :error="errors.calculation_date?.[0]" />
                    <small class="text-muted">
                        The share out is created as a draft. You can recalculate it until you approve it.
                    </small>
                </template>
            </template>
            <template v-slot:footer>
                <button v-if="cycles.length" @click="store" class="btn btn-primary" :disabled="saving">Calculate</button>
            </template>
        </MediumModal>
    </div>
</template>

<script>
export default {
    props: {
        groupId: { type: Number, required: true },
        canManage: { type: Boolean, default: false },
        cycles: { type: Array, default: () => [] },
    },

    data() {
        return {
            shareOuts: [],
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            loaded: false,
            modal: false,
            saving: false,
            errors: {},
            form: { financial_cycle_id: "", calculation_date: "" },
        };
    },

    computed: {
        base() {
            return `/groups/${this.groupId}/share-outs`;
        },
        emptyMessage() {
            return this.loaded ? "No share outs have been calculated yet." : "Loading...";
        },
    },

    created() {
        this.getShareOuts();
    },

    methods: {
        getShareOuts(page = 1) {
            axios
                .get(this.base, { params: { page } })
                .then((response) => {
                    this.shareOuts = response.data.data;
                    this.meta = response.data.meta;
                    this.loaded = true;
                })
                .catch(this.handleError);
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },

        openModal() {
            this.form = { financial_cycle_id: this.cycles[0]?.value ?? "", calculation_date: new Date().toISOString().slice(0, 10) };
            this.errors = {};
            this.modal = true;
        },

        store() {
            this.saving = true;

            axios
                .post(this.base, this.form)
                .then((response) => {
                    this.$toast.success(response.data.message);
                    window.location.href = response.data.url;
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

            this.$toast.error(data?.message ?? "Something went wrong. Please try again.");
        },
    },
};
</script>
