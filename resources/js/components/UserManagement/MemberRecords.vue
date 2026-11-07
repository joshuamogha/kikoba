<template>
    <div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th v-for="column in columns" :key="column.key" :class="{ 'text-end': column.kind === 'money' }">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in items" :key="row.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td v-for="column in columns" :key="column.key" :class="{ 'text-end': column.kind === 'money' }">
                            <StatusBadge v-if="column.kind === 'status' && row[column.key]" :status="row[column.key]" />
                            <template v-else-if="column.kind === 'money'">{{ money(row[column.key]) }}</template>
                            <template v-else>{{ row[column.key] ?? "-" }}</template>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td :colspan="columns.length + 1" class="text-center text-muted py-4">{{ loaded ? "Nothing recorded yet." : "Loading..." }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="pt-3"><Pagination :meta="meta" @change="load" /></div>
    </div>
</template>

<script>
export default {
    props: {
        memberId: { type: Number, required: true },
        type: { type: String, required: true },
        columns: { type: Array, required: true },
    },

    data() {
        return { items: [], meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }, loaded: false };
    },

    created() {
        this.load();
    },

    methods: {
        load(page = 1) {
            axios
                .get(`/members/${this.memberId}/records/${this.type}`, { params: { page } })
                .then((response) => {
                    this.items = response.data.data;
                    this.meta = response.data.meta;
                    this.loaded = true;
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },
    },
};
</script>
