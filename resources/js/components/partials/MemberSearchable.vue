<template>
    <div ref="root" class="mb-2 position-relative">
        <label v-if="label" class="form-label">{{ label }}</label>
        <div class="input-group">
            <input type="text" class="form-control" :class="{ 'is-invalid': error }" :placeholder="placeholder"
                :value="open ? query : selectedLabel" :disabled="disabled" autocomplete="off" @focus="openList"
                @input="onInput" @keydown.enter.prevent="pickFirst" @keydown.esc="close" />
            <button v-if="modelValue !== '' && modelValue !== null && !disabled" type="button"
                class="btn btn-outline-secondary" title="Clear" @click="choose('')">
                <i class="bi bi-x-lg"></i>
            </button>
            <div v-if="error" class="invalid-feedback">{{ error }}</div>
        </div>

        <ul v-if="open" class="list-group position-absolute w-100 shadow-sm overflow-auto"
            style="z-index: 1060; max-height: 220px;">
            <li v-for="option in matches" :key="option.value"
                class="list-group-item list-group-item-action py-1"
                :class="{ active: String(option.value) === String(modelValue) }" role="button"
                @mousedown.prevent="choose(option.value)">
                {{ option.label }}
            </li>
            <li v-if="!matches.length" class="list-group-item text-muted py-1">No members found.</li>
        </ul>
    </div>
</template>

<script>
export default {
    props: {
        label: { type: String, default: "Member" },
        modelValue: { type: [String, Number], default: "" },
        options: { type: Array, default: () => [] },
        placeholder: { type: String, default: "Search member by name or number..." },
        error: { type: String, default: "" },
        disabled: { type: Boolean, default: false },
    },

    emits: ["update:modelValue"],

    data() {
        return { open: false, query: "" };
    },

    computed: {
        selectedLabel() {
            return this.options.find((option) => String(option.value) === String(this.modelValue))?.label ?? "";
        },

        matches() {
            const term = this.query.trim().toLowerCase();

            return (term === "" ? this.options : this.options.filter((option) => option.label.toLowerCase().includes(term))).slice(0, 50);
        },
    },

    mounted() {
        document.addEventListener("click", this.onOutsideClick);
    },

    beforeUnmount() {
        document.removeEventListener("click", this.onOutsideClick);
    },

    methods: {
        openList() {
            if (this.disabled) {
                return;
            }

            this.query = "";
            this.open = true;
        },

        onInput(event) {
            this.query = event.target.value;
            this.open = true;
        },

        pickFirst() {
            if (this.open && this.matches.length) {
                this.choose(this.matches[0].value);
            }
        },

        choose(value) {
            this.$emit("update:modelValue", value);
            this.close();
        },

        close() {
            this.open = false;
            this.query = "";
        },

        onOutsideClick(event) {
            if (this.open && !this.$refs.root.contains(event.target)) {
                this.close();
            }
        },
    },
};
</script>
