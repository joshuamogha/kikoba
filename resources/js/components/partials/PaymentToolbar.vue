<template>
    <div class="row align-items-center g-2">
        <div class="col-md-3">
            <h4 class="mb-0">{{ title }}</h4>
        </div>
        <div class="col-md-3">
            <select :value="groupId" @change="$emit('group', $event.target.value)" class="form-select" aria-label="Group">
                <option v-if="!groups.length" value="">No groups yet</option>
                <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}</option>
            </select>
        </div>
        <div class="col-md-2">
            <SearchInput @search="$emit('search', $event)" />
        </div>
        <div class="col-md-2">
            <select @change="$emit('status', $event.target.value)" class="form-select" aria-label="Status">
                <option value="">All statuses</option>
                <option v-for="option in statuses" :key="option" :value="option">
                    {{ option.charAt(0).toUpperCase() + option.slice(1) }}
                </option>
            </select>
        </div>
        <div class="col-md-2 text-end">
            <button v-if="canManage && addLabel" @click="$emit('add')" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> {{ addLabel }}
            </button>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        title: { type: String, required: true },
        groups: { type: Array, default: () => [] },
        groupId: { type: [String, Number], default: "" },
        statuses: { type: Array, default: () => [] },
        canManage: { type: Boolean, default: false },
        addLabel: { type: String, default: "" },
    },

    emits: ["group", "search", "status", "add"],
};
</script>
