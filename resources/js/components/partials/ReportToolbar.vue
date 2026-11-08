<template>
    <div class="report-toolbar row align-items-center g-2">
        <div class="col-md-2"><h4 class="mb-0">{{ title }}</h4></div>
        <div class="col-md-2">
            <select :value="groupId" @change="$emit('group', $event.target.value)" class="form-select" aria-label="Group">
                <option v-if="!groups.length" value="">No groups yet</option>
                <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}</option>
            </select>
        </div>
        <div v-if="cycles" class="col-md-3">
            <select :value="cycleId" @change="$emit('cycle', $event.target.value)" class="form-select" aria-label="Financial cycle">
                <option v-if="!cycles.length" value="">No financial cycles yet</option>
                <option v-for="cycle in cycles" :key="cycle.value" :value="cycle.value">{{ cycle.label }}</option>
            </select>
        </div>
        <div v-if="members && members.length" class="col-md-3">
            <select :value="memberId" @change="$emit('member', $event.target.value)" class="form-select" aria-label="Member">
                <option v-for="member in members" :key="member.value" :value="member.value">{{ member.label }}</option>
            </select>
        </div>
        <div v-if="funds" class="col-md-2">
            <select :value="fundId" @change="$emit('fund', $event.target.value)" class="form-select" aria-label="Fund">
                <option v-if="!funds.length" value="">No funds</option>
                <option v-for="fund in funds" :key="fund.value" :value="fund.value">{{ fund.label }}</option>
            </select>
        </div>
        <div v-if="statuses" class="col-md-2">
            <select :value="status" @change="$emit('status', $event.target.value)" class="form-select" aria-label="Status">
                <option value="">All loans</option>
                <option v-for="option in statuses" :key="option" :value="option">{{ option.charAt(0).toUpperCase() + option.slice(1) }}</option>
            </select>
        </div>
        <div class="col text-end">
            <div class="btn-group btn-group-sm">
                <button @click="$emit('print')" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print / PDF</button>
                <a :href="csvUrl" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> CSV</a>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * The filter bar of a report. Each select is shown only when its list is passed: cycles, members, funds, statuses.
 */
export default {
    props: {
        title: { type: String, required: true },
        groups: { type: Array, default: () => [] },
        groupId: { type: [String, Number], default: "" },
        cycles: { type: Array, default: null },
        cycleId: { type: [String, Number], default: "" },
        members: { type: Array, default: null },
        memberId: { type: [String, Number], default: "" },
        funds: { type: Array, default: null },
        fundId: { type: [String, Number], default: "" },
        statuses: { type: Array, default: null },
        status: { type: String, default: "" },
        csvUrl: { type: String, required: true },
    },

    emits: ["group", "cycle", "member", "fund", "status", "print"],
};
</script>
