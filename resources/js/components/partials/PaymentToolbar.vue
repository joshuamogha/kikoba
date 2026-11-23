<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h4 class="mb-0">{{ title }}</h4>
            <div class="d-flex flex-wrap gap-2">
                <button v-if="canManage && addLabel" @click="$emit('add')" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ addLabel }}</button>
                <button v-if="canManage && bulkLabel" @click="$emit('bulk')" class="btn btn-info text-white"><i class="bi bi-people"></i> {{ bulkLabel }}</button>
                <button v-if="importAllowed && importLabel" @click="$emit('import')" class="btn btn-warning"><i class="bi bi-upload"></i> {{ importLabel }}</button>
                <slot v-if="canManage" name="actions" />
            </div>
        </div>
        <div class="row g-2">
            <div class="col-12 col-sm-6 col-lg-4">
                <select :value="groupId" @change="$emit('group', $event.target.value)" class="form-select" aria-label="Group">
                    <option v-if="!groups.length" value="">No groups yet</option>
                    <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <SearchInput @search="$emit('search', $event)" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <select @change="$emit('status', $event.target.value)" class="form-select" aria-label="Status">
                    <option value="">All statuses</option>
                    <option v-for="option in statuses" :key="option" :value="option">
                        {{ option.charAt(0).toUpperCase() + option.slice(1) }}
                    </option>
                </select>
            </div>
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
        bulkLabel: { type: String, default: "" },
        importLabel: { type: String, default: "" },
        canImport: { type: Boolean, default: null },
    },

    computed: {
        importAllowed() {
            return this.canImport ?? this.canManage;
        },
    },

    emits: ["group", "search", "status", "add", "bulk", "import"],
};
</script>
