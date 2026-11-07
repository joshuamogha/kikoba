<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <h4 class="mb-0">Member share transactions</h4>
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
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
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
                            <th>Transaction</th>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Group</th>
                            <th class="text-end">Shares</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td>{{ (meta.from || 1) + index }}</td>
                            <td>{{ item.transaction_number }}</td>
                            <td>{{ item.transaction_date }}</td>
                            <td>{{ item.member.name }}<div class="small text-muted">{{ item.member.member_number }}</div></td>
                            <td>{{ item.group.name }}<div class="small text-muted">{{ item.cycle }}</div></td>
                            <td class="text-end">{{ item.shares }} <small class="text-muted">&times; {{ money(item.price_per_share) }}</small></td>
                            <td class="text-end">{{ money(item.total_amount) }}</td>
                            <td><StatusBadge :status="item.status" /></td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="8" class="text-center text-muted py-4">{{ emptyMessage }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-body-tertiary">
            <div class="row text-center">
                <div class="col-6">
                    <span class="text-muted small">Total shares</span>
                    <div class="fw-semibold">{{ totals.shares.toLocaleString() }}</div>
                </div>
                <div class="col-6">
                    <span class="text-muted small">Total amount of shares</span>
                    <div class="fw-semibold">{{ money(totals.amount) }}</div>
                </div>
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
            totals: { shares: 0, amount: 0 },
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            loaded: false,
            search: "",
            groupId: "",
            status: "",
        };
    },

    computed: {
        emptyMessage() {
            return this.loaded ? "No share transactions found." : "Loading...";
        },
    },

    created() {
        this.load();
    },

    methods: {
        load(page = 1) {
            axios
                .get("/member-share-transactions", { params: { page, q: this.search, group_id: this.groupId, status: this.status } })
                .then((response) => {
                    this.items = response.data.data;
                    this.meta = response.data.meta;
                    this.totals = response.data.totals ?? this.totals;
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
