<template>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h4 class="mb-0">Group types</h4>
                        </div>
                        <div class="col-md-4">
                            <SearchInput @search="onSearch" />
                        </div>
                        <div class="col-md-4">
                            <button @click="openAddModal" class="btn btn-primary float-end">
                                <i class="bi bi-plus-lg"></i>
                                Group type
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th class="text-center">Groups</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="(item, index) in groupTypes" :key="item.id">
                                    <td>{{ (meta.from || 1) + index }}</td>
                                    <td>{{ item.name }}</td>
                                    <td class="text-center">{{ item.groups_count }}</td>
                                    <td>
                                        <button v-if="item.is_active" @click="activateGroupType(item.id)"
                                            class="btn btn-sm btn-danger">
                                            Deactivate
                                        </button>
                                        <button v-else @click="activateGroupType(item.id)"
                                            class="btn btn-sm btn-success">
                                            Activate
                                        </button>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button @click="editGroupType(item)" type="button"
                                                class="btn btn-sm btn-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button @click="deleteGroupType(item)" type="button"
                                                class="btn btn-sm btn-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!groupTypes.length">
                                    <td colspan="5" class="text-center text-muted py-3">No group types found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="meta.total" class="card-footer">
                    <Pagination :meta="meta" @change="getGroupTypes" />
                </div>
            </div>
        </div>

        <MediumModal v-if="addModal" @close="closeModal">
            <template v-slot:header>
                <h4 v-if="add">Add Group type</h4>
                <h4 v-else>Edit Group type</h4>
            </template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-12">
                        <BaseInput v-model="groupType.name" label="Name" type="text" :error="errors.name?.[0]" />
                    </div>
                </div>
            </template>
            <template v-slot:footer>
                <button v-if="add" @click="store" class="btn btn-primary">Save</button>
                <button v-else @click="update" class="btn btn-primary">Update</button>
            </template>
        </MediumModal>

        <ConfirmModal v-if="deleteModal" @close="closeModal">
            <template v-slot:header>
                <h4>Alert</h4>
            </template>
            <template v-slot:body>Are you sure you want to delete "{{ groupType.name }}"?</template>
            <template v-slot:footer>
                <button @click="destroy" class="btn btn-danger">Delete</button>
            </template>
        </ConfirmModal>
    </div>
</template>

<script>
const emptyGroupType = () => ({ id: null, name: "" });

export default {
    data() {
        return {
            addModal: false,
            deleteModal: false,
            add: true,
            groupTypes: [],
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            search: "",
            errors: {},
            groupType: emptyGroupType(),
        };
    },

    created() {
        this.getGroupTypes();
    },

    methods: {
        getGroupTypes(page = 1) {
            axios
                .get("/group-types", { params: { page, q: this.search } })
                .then((response) => {
                    this.groupTypes = response.data.data;
                    this.meta = response.data.meta;
                })
                .catch(this.handleError);
        },

        onSearch(query) {
            this.search = query;
            this.getGroupTypes(1);
        },

        activateGroupType(id) {
            axios
                .post(`/group-types/${id}/activate`)
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.getGroupTypes(this.meta.current_page);
                })
                .catch(this.handleError);
        },

        openAddModal() {
            this.groupType = emptyGroupType();
            this.errors = {};
            this.add = true;
            this.addModal = true;
        },

        editGroupType(groupType) {
            this.groupType = { ...groupType };
            this.errors = {};
            this.add = false;
            this.addModal = true;
        },

        deleteGroupType(groupType) {
            this.groupType = { ...groupType };
            this.deleteModal = true;
        },

        closeModal() {
            this.groupType = emptyGroupType();
            this.errors = {};
            this.add = true;
            this.addModal = false;
            this.deleteModal = false;
        },

        store() {
            axios
                .post("/group-types", { name: this.groupType.name })
                .then((response) => this.saved(response.data.message))
                .catch(this.handleError);
        },

        update() {
            axios
                .put(`/group-types/${this.groupType.id}`, { name: this.groupType.name })
                .then((response) => this.saved(response.data.message))
                .catch(this.handleError);
        },

        destroy() {
            axios
                .delete(`/group-types/${this.groupType.id}`)
                .then((response) => this.saved(response.data.message))
                .catch((error) => {
                    this.closeModal();
                    this.handleError(error);
                });
        },

        saved(message) {
            this.closeModal();
            this.$toast.success(message);
            this.getGroupTypes(this.meta.current_page);
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
