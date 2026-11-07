<template>
    <div>
        <div class="row mb-3">
            <div class="col-md-4">
                <select v-model="groupId" @change="remember" class="form-select" aria-label="Group">
                    <option v-if="!groups.length" value="">No groups yet</option>
                    <option v-for="group in groups" :key="group.value" :value="String(group.value)">{{ group.label }}</option>
                </select>
            </div>
        </div>

        <GroupSettings v-if="groupId" :key="groupId" :group-id="Number(groupId)" :can-manage="canManage" />
    </div>
</template>

<script>
import { initialGroup, rememberGroup } from "../../utils/paymentPage";

export default {
    props: {
        groups: { type: Array, default: () => [] },
    },

    data() {
        return { groupId: String(initialGroup(this.groups)) };
    },

    computed: {
        canManage() {
            return this.groups.find((group) => String(group.value) === this.groupId)?.can_manage ?? false;
        },
    },

    methods: {
        remember() {
            rememberGroup(this.groupId);
        },
    },
};
</script>
