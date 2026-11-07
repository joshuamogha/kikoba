<template>
    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">System roles</h4>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th class="text-center">Holders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in systemRoles" :key="role.id">
                                <td>
                                    <strong>{{ role.name }}</strong>
                                    <div class="small text-muted">{{ role.description }}</div>
                                </td>
                                <td class="text-center">{{ role.members_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    System roles are shared by every group and cannot be changed.
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Your roles</h4>
                        <button v-if="canManage" @click="openAddModal" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i> Role
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th class="text-center">Holders</th>
                                <th>Status</th>
                                <th v-if="canManage">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in customRoles" :key="role.id">
                                <td>
                                    <strong>{{ role.name }}</strong>
                                    <div class="small text-muted">{{ role.description }}</div>
                                </td>
                                <td class="text-center">{{ role.members_count }}</td>
                                <td>
                                    <StatusBadge :status="role.status" />
                                </td>
                                <td v-if="canManage">
                                    <div class="btn-group btn-group-sm">
                                        <button @click="toggle(role)" class="btn"
                                            :class="role.status === 'active' ? 'btn-warning' : 'btn-success'"
                                            :title="role.status === 'active' ? 'Deactivate' : 'Activate'">
                                            <i class="bi"
                                                :class="role.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                                        </button>
                                        <button @click="openEditModal(role)" class="btn btn-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button @click="openDeleteModal(role)" class="btn btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!customRoles.length">
                                <td :colspan="canManage ? 4 : 3" class="text-center text-muted py-4">
                                    No custom roles yet, for example Auditor or Loan Officer.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <MediumModal v-if="formModal" @close="closeModals">
            <template v-slot:header>
                <h4>{{ role.id ? "Edit role" : "Add role" }}</h4>
            </template>
            <template v-slot:body>
                <BaseInput v-model="role.name" label="Name" :error="errors.name?.[0]" />
                <BaseInput v-model="role.description" label="Description (optional)" :error="errors.description?.[0]" />
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary">{{ role.id ? "Update" : "Save" }}</button>
            </template>
        </MediumModal>

        <ConfirmModal v-if="deleteModal" @close="closeModals">
            <template v-slot:header>
                <h4>Alert</h4>
            </template>
            <template v-slot:body>Delete the role "{{ role.name }}"?</template>
            <template v-slot:footer>
                <button @click="destroy" class="btn btn-danger">Delete</button>
            </template>
        </ConfirmModal>
    </div>
</template>

<script>
const emptyRole = () => ({ id: null, name: "", description: "", status: "active" });

export default {
    props: {
        groupId: { type: Number, required: true },
        canManage: { type: Boolean, default: false },
    },

    data() {
        return {
            roles: [],
            formModal: false,
            deleteModal: false,
            errors: {},
            role: emptyRole(),
        };
    },

    computed: {
        base() {
            return `/groups/${this.groupId}/roles`;
        },
        systemRoles() {
            return this.roles.filter((role) => role.is_system_role);
        },
        customRoles() {
            return this.roles.filter((role) => !role.is_system_role);
        },
    },

    created() {
        this.getRoles();
    },

    methods: {
        getRoles() {
            axios
                .get(this.base)
                .then((response) => (this.roles = response.data.data))
                .catch(this.handleError);
        },

        openAddModal() {
            this.role = emptyRole();
            this.errors = {};
            this.formModal = true;
        },

        openEditModal(role) {
            this.role = { ...role, description: role.description ?? "" };
            this.errors = {};
            this.formModal = true;
        },

        openDeleteModal(role) {
            this.role = { ...role };
            this.deleteModal = true;
        },

        save() {
            const request = this.role.id
                ? axios.put(`${this.base}/${this.role.id}`, this.role)
                : axios.post(this.base, { name: this.role.name, description: this.role.description });

            request.then((response) => this.done(response.data.message)).catch(this.handleError);
        },

        toggle(role) {
            axios
                .put(`${this.base}/${role.id}`, {
                    name: role.name,
                    description: role.description,
                    status: role.status === "active" ? "inactive" : "active",
                })
                .then((response) => this.done(response.data.message))
                .catch(this.handleError);
        },

        destroy() {
            axios
                .delete(`${this.base}/${this.role.id}`)
                .then((response) => this.done(response.data.message))
                .catch((error) => {
                    this.closeModals();
                    this.handleError(error);
                });
        },

        done(message) {
            this.closeModals();
            this.$toast.success(message);
            this.getRoles();
        },

        closeModals() {
            this.formModal = false;
            this.deleteModal = false;
            this.errors = {};
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
