<template>
    <MediumModal @close="$emit('close')">
        <template v-slot:header>
            <h4>Roles &middot; {{ member.name }}</h4>
        </template>
        <template v-slot:body>
            <h6>Current roles</h6>
            <ul class="list-group mb-3">
                <li v-for="role in member.roles" :key="role.assignment_id"
                    class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        {{ role.name }}
                        <small class="text-muted ms-1">since {{ role.since }}</small>
                    </span>
                    <button v-if="role.name !== 'Member'" @click="end(role)" class="btn btn-sm btn-outline-danger"
                        title="End this role">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </li>
                <li v-if="!member.roles.length" class="list-group-item text-muted">No active roles.</li>
            </ul>

            <h6>Assign a role</h6>
            <BaseSelect v-model="roleId" label="" :options="options" placeholder="Choose a role..."
                :error="errors.group_role_id?.[0]" />
            <small class="text-muted">Giving someone Chairperson hands the role over from the current Chairperson.</small>
            <small v-if="!member.has_account" class="text-muted d-block mt-1">
                Chairperson, Vice Chairperson and Secretary need a linked account, because leaders must be able to sign in.
            </small>
        </template>
        <template v-slot:footer>
            <button @click="assign" class="btn btn-primary" :disabled="!roleId">Assign</button>
        </template>
    </MediumModal>
</template>

<script>
export default {
    props: {
        groupId: { type: Number, required: true },
        member: { type: Object, required: true },
        assignableRoles: { type: Array, default: () => [] },
    },

    emits: ["close", "changed"],

    data() {
        return {
            roleId: "",
            errors: {},
        };
    },

    computed: {
        options() {
            const held = this.member.roles.map((role) => role.role_id);

            // Leaders must be able to sign in, so members without an account can't hold these.
            const leadership = ["Chairperson", "Vice Chairperson", "Secretary"];

            return this.assignableRoles
                .filter((role) => !held.includes(role.id))
                .filter((role) => this.member.has_account || role.custom || !leadership.includes(role.name))
                .map((role) => ({ value: role.id, label: role.custom ? `${role.name} (custom)` : role.name }));
        },
        url() {
            return `/groups/${this.groupId}/members/${this.member.id}/roles`;
        },
    },

    methods: {
        assign() {
            axios
                .post(this.url, { group_role_id: this.roleId })
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.roleId = "";
                    this.errors = {};
                    this.$emit("changed");
                })
                .catch(this.handleError);
        },

        end(role) {
            axios
                .delete(`${this.url}/${role.assignment_id}`)
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.$emit("changed");
                })
                .catch(this.handleError);
        },

        handleError(error) {
            const data = error.response?.data;

            if (error.response?.status === 422 && data?.errors) {
                this.errors = data.errors;
                return;
            }

            this.$toast.error(data?.error ?? data?.message ?? "Something went wrong. Please try again.");
        },
    },
};
</script>
