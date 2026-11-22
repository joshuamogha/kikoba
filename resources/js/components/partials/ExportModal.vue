<template>
    <MediumModal @close="$emit('close')">
        <template v-slot:header><h4>{{ title }}</h4></template>
        <template v-slot:body>
            <p class="small text-muted">Exports what is on the screen (the current filters). Choose the columns to include.</p>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label mb-0">Columns</label>
                <div class="btn-group btn-group-sm">
                    <button @click="chosen = columns.map((column) => column.value)" class="btn btn-outline-secondary">All</button>
                    <button @click="chosen = []" class="btn btn-outline-secondary">None</button>
                </div>
            </div>
            <div class="row" style="max-height: 40vh; overflow-y: auto">
                <div v-for="column in columns" :key="column.value" class="col-6 col-md-4">
                    <div class="form-check">
                        <input :id="`export-${column.value}`" v-model="chosen" :value="column.value" type="checkbox" class="form-check-input" />
                        <label :for="`export-${column.value}`" class="form-check-label">{{ column.label }}</label>
                    </div>
                </div>
            </div>
            <label class="form-label mt-3">Format</label>
            <div class="btn-group w-100">
                <button v-for="option in options" :key="option.key" @click="format = option.key" class="btn" :class="format === option.key ? `btn-${option.color}` : `btn-outline-${option.color}`">
                    <i :class="`bi ${option.icon}`"></i> {{ option.label }}
                </button>
            </div>
            <p v-if="format === 'pdf'" class="small text-muted mt-2 mb-0">The PDF opens in a new tab: choose "Save as PDF" in the print window.</p>
        </template>
        <template v-slot:footer><button @click="run" class="btn btn-primary" :disabled="!chosen.length">Export</button></template>
    </MediumModal>
</template>

<script>
/**
 * Choose the columns and the format (Excel, PDF, CSV) of an export. The file is requested from `url` with the same filters as the
 * screen (`params`), the chosen `format` and `columns[]`.
 */
const FORMATS = [
    { key: "xlsx", label: "Excel", icon: "bi-file-earmark-excel", color: "success" },
    { key: "pdf", label: "PDF", icon: "bi-file-earmark-pdf", color: "danger" },
    { key: "csv", label: "CSV", icon: "bi-filetype-csv", color: "secondary" },
];

export default {
    props: {
        title: { type: String, default: "Export" },
        columns: { type: Array, required: true },
        url: { type: String, required: true },
        params: { type: Object, default: () => ({}) },
        formats: { type: Array, default: () => ["xlsx", "pdf"] },
    },

    emits: ["close"],

    data() {
        return { chosen: this.columns.map((column) => column.value), format: this.formats[0] };
    },

    computed: {
        options() {
            return FORMATS.filter((option) => this.formats.includes(option.key));
        },
    },

    methods: {
        run() {
            const query = new URLSearchParams();

            Object.entries(this.params).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== "") {
                    query.append(key, value);
                }
            });
            query.set("format", this.format);
            this.chosen.forEach((column) => query.append("columns[]", column));

            const link = `${this.url}?${query}`;

            if (this.format === "pdf") {
                window.open(link, "_blank");
            } else {
                window.location.href = link;
            }

            this.$emit("close");
        },
    },
};
</script>
