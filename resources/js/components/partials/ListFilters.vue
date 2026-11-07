<template>
    <div class="row g-2 align-items-end">
        <div v-if="members.length" class="col-md-3">
            <MemberSearchable :model-value="modelValue.group_member_id ?? ''" label="" :options="members" placeholder="Filter by member..."
                @update:modelValue="update('group_member_id', $event)" />
        </div>
        <div class="col-md-2">
            <MonthPicker :model-value="modelValue.month ?? ''" label="" placeholder="Filter by month" @update:modelValue="update('month', $event)" />
        </div>
        <div v-if="funds.length" class="col-md-3">
            <div class="mb-2">
                <select :value="modelValue.group_fund_id ?? ''" class="form-select" aria-label="Fund" @change="update('group_fund_id', $event.target.value)">
                    <option value="">All funds</option>
                    <option v-for="fund in funds" :key="fund.value" :value="fund.value">{{ fund.label }}</option>
                </select>
            </div>
        </div>
        <div v-if="cycles.length" class="col-md-3">
            <div class="mb-2">
                <select :value="modelValue.financial_cycle_id ?? ''" class="form-select" aria-label="Financial cycle" @change="update('financial_cycle_id', $event.target.value)">
                    <option value="">All financial cycles</option>
                    <option v-for="cycle in cycles" :key="cycle.value" :value="cycle.value">{{ cycle.label }}</option>
                </select>
            </div>
        </div>
        <div class="col-md-1">
            <div class="mb-2">
                <button v-if="hasFilters" type="button" class="btn btn-outline-secondary w-100" title="Clear filters" @click="$emit('update:modelValue', {})">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Filter row for member, month and (optionally) financial cycle. The value is an object holding only the filters that are set.
 */
export default {
    props: {
        modelValue: { type: Object, default: () => ({}) },
        members: { type: Array, default: () => [] },
        cycles: { type: Array, default: () => [] },
        funds: { type: Array, default: () => [] },
    },

    emits: ["update:modelValue"],

    computed: {
        hasFilters() {
            return Object.values(this.modelValue).some((value) => value !== "" && value !== null && value !== undefined);
        },
    },

    methods: {
        update(key, value) {
            this.$emit("update:modelValue", { ...this.modelValue, [key]: value });
        },
    },
};
</script>
