<template>
    <div class="mb-2">
        <label class="form-label">{{ label }}</label>
        <input ref="input" type="text" class="form-control" :placeholder="placeholder" />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
    </div>
</template>

<script>
import flatpickr from "flatpickr";
import monthSelectPlugin from "flatpickr/dist/plugins/monthSelect";
import "flatpickr/dist/plugins/monthSelect/style.css";

/**
 * Picks a month and emits it as YYYY-MM.
 */
export default {
    props: {
        label: { type: String, default: "" },
        modelValue: { type: String, default: "" },
        placeholder: { type: String, default: "Select month" },
        error: { type: String, default: "" },
    },

    emits: ["update:modelValue"],

    mounted() {
        this.picker = flatpickr(this.$refs.input, {
            defaultDate: this.modelValue ? `${this.modelValue}-01` : null,
            plugins: [monthSelectPlugin({ shorthand: true, dateFormat: "Y-m", altFormat: "F Y" })],
            altInput: true,
            altInputClass: "form-control",
            onChange: (selectedDates, dateString) => this.$emit("update:modelValue", dateString),
        });

        this.markInvalid();
    },

    beforeUnmount() {
        this.picker?.destroy();
    },

    watch: {
        modelValue(value) {
            if (value && value !== this.$refs.input.value) {
                this.picker.setDate(`${value}-01`, false);
            }
        },
        error() {
            this.markInvalid();
        },
    },

    methods: {
        markInvalid() {
            this.picker?.altInput?.classList.toggle("is-invalid", Boolean(this.error));
        },
    },
};
</script>
