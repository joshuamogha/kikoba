<template>
    <LargeModal @close="$emit('close')">
        <template v-slot:header><h4>{{ title }}</h4></template>
        <template v-slot:body>
            <div v-if="fields.length" class="row mb-2">
                <div v-for="field in fields" :key="field.name" class="col-md-6">
                    <div v-if="field.type === 'checkbox'" class="form-check mt-4">
                        <input :id="`import-${field.name}`" v-model="values[field.name]" type="checkbox" class="form-check-input" />
                        <label :for="`import-${field.name}`" class="form-check-label">{{ field.label }}</label>
                    </div>
                    <div v-else class="mb-2">
                        <label class="form-label">{{ field.label }}<span v-if="field.required" class="text-danger"> *</span></label>
                        <select v-model="values[field.name]" class="form-select">
                            <option value="">{{ field.placeholder ?? "Select..." }}</option>
                            <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">1. Download the template</h6>
                <a :href="downloadUrl" class="btn btn-outline-primary btn-sm" download><i class="bi bi-download"></i> Download template</a>
            </div>
            <table class="table table-sm table-bordered mb-3">
                <thead><tr><th>Column</th><th>Required</th><th>What to enter</th><th>Example</th></tr></thead>
                <tbody>
                    <tr v-for="column in columns" :key="column.name">
                        <td><code>{{ column.name }}</code></td>
                        <td>{{ column.required ? "Yes" : "No" }}</td>
                        <td>{{ column.description }}</td>
                        <td>{{ column.example }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="note" class="small text-muted">{{ note }}</p>

            <h6>2. Upload the file</h6>
            <div class="dropzone-box border border-2 rounded p-4 text-center" :class="dragging ? 'border-primary bg-body-tertiary' : 'border-secondary-subtle'"
                role="button" tabindex="0" @click="$refs.fileInput.click()" @keydown.enter="$refs.fileInput.click()"
                @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
                <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                <div v-if="!file">Drag and drop the CSV or Excel (.xlsx) file here, or <span class="text-primary">click to choose</span></div>
                <div v-else><strong>{{ file.name }}</strong> <small class="text-muted">({{ Math.ceil(file.size / 1024) }} KB)</small></div>
                <input ref="fileInput" type="file" accept=".csv,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="d-none" @change="onPick" />
            </div>
            <div v-if="error" class="text-danger small mt-2">{{ error }}</div>

            <div v-if="result" class="mt-3">
                <div class="alert" :class="result.skipped.length ? 'alert-warning' : 'alert-success'">
                    {{ result.imported }} imported, {{ result.skipped.length }} skipped.
                </div>
                <table v-if="result.skipped.length" class="table table-sm table-striped">
                    <thead><tr><th>Row</th><th>Name</th><th>Phone</th><th>Why it was skipped</th></tr></thead>
                    <tbody>
                        <tr v-for="row in result.skipped" :key="row.row"><td>{{ row.row }}</td><td>{{ row.name }}</td><td>{{ row.phone }}</td><td>{{ row.reason }}</td></tr>
                    </tbody>
                </table>
            </div>
        </template>
        <template v-slot:footer>
            <button @click="upload" class="btn btn-primary" :disabled="!file || saving || missingField"><i class="bi bi-upload"></i> Upload and import</button>
        </template>
    </LargeModal>
</template>

<script>
export default {
    props: {
        title: { type: String, required: true },
        templateUrl: { type: String, required: true },
        uploadUrl: { type: String, required: true },
        groupId: { type: [String, Number], required: true },
        columns: { type: Array, required: true },
        note: { type: String, default: "" },
        /** Extra choices sent with the upload and the template link: { name, label, type: "select" | "checkbox", options, required, default }. */
        fields: { type: Array, default: () => [] },
    },

    emits: ["close", "imported"],

    data() {
        return {
            file: null,
            dragging: false,
            result: null,
            error: "",
            saving: false,
            values: Object.fromEntries(this.fields.map((field) => [field.name, field.default ?? (field.type === "checkbox" ? false : "")])),
        };
    },

    computed: {
        missingField() {
            return this.fields.some((field) => field.required && !this.values[field.name]);
        },

        downloadUrl() {
            const query = new URLSearchParams({ group_id: this.groupId });

            this.fields.forEach((field) => query.set(field.name, field.type === "checkbox" ? (this.values[field.name] ? "1" : "0") : this.values[field.name]));

            return `${this.templateUrl}${this.templateUrl.includes("?") ? "&" : "?"}${query.toString()}`;
        },
    },

    methods: {
        onPick(event) {
            this.setFile(event.target.files[0]);
            event.target.value = "";
        },

        onDrop(event) {
            this.dragging = false;
            this.setFile(event.dataTransfer.files[0]);
        },

        setFile(file) {
            this.result = null;
            this.error = "";

            if (file && !/\.(csv|txt|xlsx)$/i.test(file.name)) {
                this.file = null;
                this.error = "Choose a .csv or .xlsx file. Download the template to start from.";

                return;
            }

            this.file = file ?? null;
        },

        upload() {
            const body = new FormData();
            body.append("group_id", this.groupId);
            body.append("file", this.file);
            this.fields.forEach((field) => body.append(field.name, field.type === "checkbox" ? (this.values[field.name] ? "1" : "0") : this.values[field.name]));

            this.saving = true;
            this.error = "";

            axios
                .post(this.uploadUrl, body)
                .then((response) => {
                    this.result = response.data.data;
                    this.file = null;
                    this.$toast.success(response.data.message);
                    this.$emit("imported");
                })
                .catch((error) => {
                    this.error = error.response?.data?.errors?.file?.[0] ?? error.response?.data?.message ?? "Something went wrong. Please try again.";
                })
                .finally(() => (this.saving = false));
        },
    },
};
</script>

<style scoped>
.dropzone-box {
    cursor: pointer;
    border-style: dashed !important;
}
</style>
