<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-3">
                    <h4 class="mb-0">Members</h4>
                </div>
                <div class="col-md-3">
                    <select :value="groupId" @change="onGroup($event.target.value)" class="form-select"
                        aria-label="Group">
                        <option v-if="!groups.length" value="">No groups yet</option>
                        <option v-for="group in groups" :key="group.value" :value="group.value">{{ group.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <SearchInput @search="onSearch" />
                </div>
                <div class="col-md-2">
                    <select @change="onStatus($event.target.value)" class="form-select" aria-label="Status">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="exited">Exited</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <div v-if="canManage" class="btn-group btn-group-sm">
                        <button @click="openAdd" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Member</button>
                        <button @click="openImport" class="btn btn-outline-primary"><i class="bi bi-upload"></i>
                            Import</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.member_number }}</td>
                        <td>{{ item.name }}</td>
                        <td>{{ item.phone }}</td>
                        <td>{{ item.membership_type.name }}</td>
                        <td><span class="badge" :class="item.has_account ? 'text-bg-success' : 'text-bg-secondary'">{{
                            item.has_account ? "Linked" : "No account" }}</span></td>
                        <td>
                            <StatusBadge :status="item.membership_status" />
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a v-if="canManage || item.is_self" :href="`/members/${item.id}`" class="btn btn-info"
                                    title="View member"><i class="bi bi-eye"></i></a>
                                <template v-if="canManage">
                                    <button @click="openEdit(item)" class="btn btn-secondary" title="Edit"><i
                                            class="bi bi-pencil"></i></button>
                                    <button v-if="item.membership_status === 'active'"
                                        @click="setStatus(item, 'suspended')" class="btn btn-warning"
                                        title="Deactivate"><i class="bi bi-pause-circle"></i></button>
                                    <button v-else @click="setStatus(item, 'active')" class="btn btn-success"
                                        title="Activate"><i class="bi bi-play-circle"></i></button>
                                    <button @click="deleting = item" class="btn btn-danger" title="Delete"><i
                                            class="bi bi-trash"></i></button>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td colspan="10" class="text-center text-muted py-4">{{ emptyMessage }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <Pagination v-if="meta.total" :meta="meta" @change="load" />
            <div v-if="groupId" class="text-center mt-2">
                <a :href="`/groups/${groupId}/members`" class="small">Manage roles, status and details in the group's
                    members
                    page</a>
            </div>
        </div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header>
                <h4>{{ form.id ? "Edit member" : "Add member" }}</h4>
            </template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6">
                        <BaseInput v-model="form.name" label="Full name" :error="errors.name?.[0]" />
                    </div>
                    <div v-if="!form.id" class="col-md-6">
                        <BaseInput v-model="form.login" label="Phone number or email" placeholder="0712345678"
                            :error="errors.login?.[0]" />
                    </div>
                    <div v-else class="col-md-6">
                        <BaseInput v-model="form.phone_number" label="Phone number" :disabled="form.has_account"
                            :error="errors.phone_number?.[0]" />
                    </div>
                    <div class="col-md-6">
                        <BaseSelect v-model="form.membership_type_id" label="Membership type"
                            :options="options.membership_types" :error="errors.membership_type_id?.[0]" />
                    </div>
                    <div v-if="!form.id" class="col-md-6">
                        <DatePicker v-model="form.joined_at" label="Joined date" :error="errors.joined_at?.[0]" />
                    </div>
                </div>
                <p class="small text-muted mb-0">A phone number or email that belongs to a registered user links to
                    their account. Any other phone number adds a member without an account.</p>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ?
                "Update member" : "Add member" }}</button></template>
        </LargeModal>

        <ConfirmModal v-if="deleting" @close="deleting = null">
            <template v-slot:header>
                <h4>Alert</h4>
            </template>
            <template v-slot:body>
                Delete {{ deleting.name }} ({{ deleting.member_number }})? Only members with no contributions, loans,
                shares or fines can be deleted. Otherwise deactivate them.
            </template>
            <template v-slot:footer><button @click="destroy" class="btn btn-danger"
                    :disabled="saving">Delete</button></template>
        </ConfirmModal>

        <ImportModal v-if="importModal" title="Import members" template-url="/members/import/template"
            upload-url="/members/import" :group-id="groupId" :columns="importColumns"
            note="Keep the first row as the headings. Up to 500 members per file. People already in the group are skipped. New members get the group's Ordinary Member type and the Member role."
            @close="importModal = false" @imported="load(1)" />
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/members",
            deleting: null,
            importModal: false,
            importColumns: [
                { name: "name", required: true, description: "The member's full name.", example: "Asha Juma" },
                { name: "phone_number", required: true, description: "A phone number of 9 to 15 digits, with or without a leading +. Spaces and dashes are ignored. A number that belongs to a registered user links to their account.", example: "0712345678 or +255754123456" },
            ],
        };
    },

    methods: {
        openAdd() {
            this.openForm({ name: "", login: "", membership_type_id: this.options.membership_types?.[0]?.value ?? "", joined_at: new Date().toISOString().slice(0, 10) });
        },

        openEdit(item) {
            this.openForm({ id: item.id, name: item.name, phone_number: item.phone ?? "", has_account: item.has_account, membership_type_id: item.membership_type.id });
        },

        save() {
            if (!this.form.id) {
                this.submit(axios.post(`/groups/${this.groupId}/members`, this.form));

                return;
            }

            const { name, phone_number, membership_type_id, has_account } = this.form;

            this.submit(axios.put(`/groups/${this.groupId}/members/${this.form.id}`, { name, membership_type_id, ...(has_account ? {} : { phone_number }) }));
        },

        setStatus(item, status) {
            this.change(axios.patch(`/groups/${this.groupId}/members/${item.id}/status`, { status }));
        },

        destroy() {
            const member = this.deleting;

            this.deleting = null;
            this.change(axios.delete(`/groups/${this.groupId}/members/${member.id}`));
        },

        change(request) {
            request
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.load(this.meta.current_page);
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        openImport() {
            this.importModal = true;
        },
    },
};
</script>
