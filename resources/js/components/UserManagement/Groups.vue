<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <h4 class="mb-0">{{ showAll ? "All groups" : "My groups" }}</h4>
                </div>
                <div class="col-md-4">
                    <SearchInput @search="onSearch" />
                </div>
                <div class="col-md-4 text-md-end">
                    <button @click="openAddModal" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Group
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Group</th>
                            <th>Type</th>
                            <th class="text-center">Members</th>
                            <th>Your roles</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(group, index) in groups" :key="group.id">
                            <td>{{ (meta.from || 1) + index }}</td>
                            <td><a :href="group.url" class="fw-semibold">{{ group.name }}</a></td>
                            <td>{{ group.group_type?.name }}</td>
                            <td class="text-center">{{ group.members_count }}</td>
                            <td>
                                <span v-for="role in group.my_roles" :key="role" class="badge text-bg-primary me-1">{{
                                    role }}</span>
                                <span v-if="!group.my_roles?.length" class="text-muted">&mdash;</span>
                            </td>
                            <td>
                                <StatusBadge :status="group.status" />
                            </td>
                        </tr>
                        <tr v-if="!groups.length">
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ emptyMessage }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="meta.total" class="card-footer">
            <Pagination :meta="meta" @change="getGroups" />
        </div>

        <LargeModal v-if="addModal" @close="closeModal">
            <template v-slot:header>
                <h4>Add group</h4>
            </template>
            <template v-slot:body>
                <p class="text-muted">You will become the Chairperson and can then add members and roles.</p>
                <div class="row">
                    <div class="col-md-6">
                        <BaseInput v-model="form.name" label="Group name" :error="errors.name?.[0]" />
                    </div>
                    <div class="col-md-6">
                        <BaseSelect v-model="form.group_type_id" label="Group type" :options="typeOptions"
                            :error="errors.group_type_id?.[0]" />
                    </div>
                    <div class="col-md-6">
                        <BaseInput v-model="form.registration_number" label="Registration number (optional)"
                            :error="errors.registration_number?.[0]" />
                    </div>
                    <div class="col-md-3">
                        <DatePicker v-model="form.established_date" label="Established" max-date="today"
                            :error="errors.established_date?.[0]" />
                    </div>
                    <div class="col-md-3">
                        <BaseInput v-model="form.maximum_members" type="number" min="2" label="Maximum members"
                            :error="errors.maximum_members?.[0]" />
                    </div>
                    <div class="col-md-12">
                        <BaseInput v-model="form.description" label="Description (optional)"
                            :error="errors.description?.[0]" />
                    </div>
                </div>
            </template>
            <template v-slot:footer>
                <button @click="store" class="btn btn-primary" :disabled="saving">Create group</button>
            </template>
        </LargeModal>
    </div>
</template>

<script>
const emptyForm = () => ({
    name: "",
    group_type_id: "",
    registration_number: "",
    established_date: "",
    maximum_members: "",
    description: "",
});

export default {
    props: {
        groupTypes: { type: Array, default: () => [] },
        showAll: { type: Boolean, default: false },
    },

    data() {
        return {
            groups: [],
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            search: "",
            addModal: false,
            saving: false,
            errors: {},
            form: emptyForm(),
        };
    },

    computed: {
        emptyMessage() {
            return this.showAll
                ? "No groups yet."
                : "You are not in any group yet. Create one to get started.";
        },
        typeOptions() {
            return this.groupTypes.map((type) => ({ value: type.id, label: type.name }));
        },
    },

    created() {
        this.getGroups();
    },

    methods: {
        getGroups(page = 1) {
            axios
                .get("/groups", { params: { page, q: this.search, scope: this.showAll ? "all" : undefined } })
                .then((response) => {
                    this.groups = response.data.data;
                    this.meta = response.data.meta;
                })
                .catch(this.handleError);
        },

        onSearch(query) {
            this.search = query;
            this.getGroups(1);
        },

        openAddModal() {
            this.form = emptyForm();
            this.errors = {};
            this.addModal = true;
        },

        closeModal() {
            this.addModal = false;
            this.errors = {};
        },

        store() {
            this.saving = true;

            axios
                .post("/groups", this.form)
                .then((response) => {
                    this.$toast.success(response.data.message);
                    window.location.href = response.data.redirect;
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
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
