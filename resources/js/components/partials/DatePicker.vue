<template>
    <div class="mb-2">
        <label class="form-label">{{ label }}</label>
        <input ref="input" type="text" class="form-control" :placeholder="placeholder" />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
    </div>
</template>

<script>
import flatpickr from "flatpickr";

export default {
    props: {
        label: { type: String, default: "" },
        modelValue: { type: String, default: "" },
        placeholder: { type: String, default: "Select date" },
        error: { type: String, default: "" },
        minDate: { type: [String, Date], default: null },
        maxDate: { type: [String, Date], default: null },
    },

    emits: ["update:modelValue"],

    mounted() {
        this.picker = flatpickr(this.$refs.input, {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d M Y",
            altInputClass: "form-control",
            allowInput: true,
            defaultDate: this.modelValue || null,
            minDate: this.minDate,
            maxDate: this.maxDate,
            onChange: (selectedDates, dateString) => this.$emit("update:modelValue", dateString),
        });

        this.markInvalid();
    },

    beforeUnmount() {
        this.picker?.destroy();
    },

    watch: {
        modelValue(value) {
            if (value !== this.$refs.input.value) {
                this.picker.setDate(value || null, false);
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
