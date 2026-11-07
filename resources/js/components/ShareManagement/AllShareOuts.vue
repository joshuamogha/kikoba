<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <h4 class="mb-0">Share outs</h4>
                </div>
                <div class="col-md-3">
                    <SearchInput @search="onSearch" />
                </div>
                <div class="col-md-2">
                    <select v-model="groupId" @change="load(1)" class="form-select">
                        <option value="">All my groups</option>
                        <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select v-model="status" @change="load(1)" class="form-select">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                    </select>
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
                            <th>Group</th>
                            <th>Cycle</th>
                            <th>Calculated on</th>
                            <th class="text-end">To distribute</th>
                            <th class="text-end">Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td>{{ (meta.from || 1) + index }}</td>
                            <td><a :href="item.url">{{ item.share_out_number }}</a></td>
                            <td>{{ item.group.name }}</td>
                            <td>{{ item.cycle.name }}</td>
                            <td>{{ item.calculation_date }}</td>
                            <td class="text-end">{{ money(item.totals.distributable) }}</td>
                            <td class="text-end">{{ money(item.amount_paid) }}</td>
                            <td><StatusBadge :status="item.status" /></td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="8" class="text-center text-muted py-4">{{ emptyMessage }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="meta.total" class="card-footer">
            <Pagination :meta="meta" @change="load" />
        </div>
    </div>
</template>

<script>
export default {
    props: {
        groups: { type: Array, default: () => [] },
    },

    data() {
        return {
            items: [],
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            loaded: false,
            search: "",
            groupId: "",
            status: "",
        };
    },

    computed: {
        emptyMessage() {
            return this.loaded ? "No share outs found." : "Loading...";
        },
    },

    created() {
        this.load();
    },

    methods: {
        load(page = 1) {
            axios
                .get("/share-outs", { params: { page, q: this.search, group_id: this.groupId, status: this.status } })
                .then((response) => {
                    this.items = response.data.data;
                    this.meta = response.data.meta;
                    this.loaded = true;
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        onSearch(query) {
            this.search = query;
            this.load(1);
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },
    },
};
</script>
