<template>
  <div class="mb-2">
    <label class="form-label">{{ label }}</label>
    <input
      v-bind="$attrs"
      class="form-control"
      :class="{ 'is-invalid': error }"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <div v-if="error" class="invalid-feedback">{{ error }}</div>
  </div>
</template>

<script>
export default {
  inheritAttrs: false,

  props: {
    label: {
      type: String,
      default: "",
    },
    modelValue: {
      type: [String, Number],
      default: "",
    },
    error: {
      type: String,
      default: "",
    },
  },

  emits: ["update:modelValue"],
};
</script>
