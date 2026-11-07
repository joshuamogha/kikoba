<template>
    <div class="mb-2">
        <label class="form-label">{{ label }}</label>
        <select
            v-bind="$attrs"
            class="form-select"
            :class="{ 'is-invalid': error }"
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <div v-if="error" class="invalid-feedback">{{ error }}</div>
    </div>
</template>

<script>
export default {
    inheritAttrs: false,

    props: {
        label: { type: String, default: "" },
        modelValue: { type: [String, Number], default: "" },
        options: { type: Array, default: () => [] },
        placeholder: { type: String, default: "Select..." },
        error: { type: String, default: "" },
    },

    emits: ["update:modelValue"],
};
</script>
