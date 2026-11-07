<template>
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <h4 class="mb-0">Members</h4>
                </div>
                <div class="col-md-3">
                    <SearchInput @search="onSearch" />
                </div>
                <div class="col-md-2">
                    <select v-model="status" @change="getMembers(1)" class="form-select">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="exited">Exited</option>
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <button v-if="canManage" @click="openAddModal" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add member
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
                            <th>Member</th>
                            <th>No.</th>
                            <th>Type</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th v-if="canManage">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(member, index) in members" :key="member.id">
                            <td>{{ (meta.from || 1) + index }}</td>
                            <td>
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-bg-primary fw-semibold me-2"
                                    style="width: 32px; height: 32px; font-size: 12px;">{{ member.initials }}</span>
                                {{ member.name }}
                                <span v-if="member.is_self" class="badge text-bg-light border ms-1">You</span>
                                <span v-if="!member.has_account" class="badge text-bg-light border ms-1"
                                    title="Added by name and phone number; not linked to a registered account">No account</span>
                                <div class="small text-muted">{{ member.phone }}<span v-if="member.email"> &middot; {{ member.email }}</span></div>
                            </td>
                            <td>{{ member.member_number }}</td>
                            <td>{{ member.membership_type.name }}</td>
                            <td>
                                <template v-for="role in member.roles" :key="role.assignment_id">
                                    <span v-if="role.name !== 'Member'" class="badge text-bg-primary me-1">{{ role.name }}</span>
                                </template>
                                <span v-if="!leadershipRoles(member).length" class="text-muted small">Member</span>
                            </td>
                            <td>
                                <StatusBadge :status="member.membership_status" />
                                <div v-if="member.exit_reason" class="small text-muted">{{ member.exit_reason }}</div>
                            </td>
                            <td>{{ member.joined_at }}</td>
                            <td v-if="canManage">
                                <div class="btn-group btn-group-sm">
                                    <button @click="openEditModal(member)" class="btn btn-secondary" title="Edit member">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button v-if="!member.has_account && member.membership_status !== 'exited'"
                                        @click="openLinkModal(member)" class="btn btn-primary" title="Link to a registered account">
                                        <i class="bi bi-link-45deg"></i>
                                    </button>
                                    <button v-if="member.membership_status === 'active'" @click="openRolesModal(member)"
                                        class="btn btn-info" title="Roles">
                                        <i class="bi bi-person-badge"></i>
                                    </button>
                                    <button v-if="member.membership_status === 'active'" @click="setStatus(member, 'suspended')"
                                        class="btn btn-warning" title="Suspend">
                                        <i class="bi bi-pause-circle"></i>
                                    </button>
                                    <button v-if="member.membership_status !== 'active'" @click="setStatus(member, 'active')"
                                        class="btn btn-success" title="Reinstate">
                                        <i class="bi bi-play-circle"></i>
                                    </button>
                                    <button v-if="member.membership_status !== 'exited'" @click="openExitModal(member)"
                                        class="btn btn-danger" title="Remove from group">
                                        <i class="bi bi-box-arrow-right"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!members.length">
                            <td :colspan="canManage ? 8 : 7" class="text-center text-muted py-4">No members found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="meta.total" class="card-footer">
            <Pagination :meta="meta" @change="getMembers" />
        </div>

        <MediumModal v-if="addModal" @close="closeModals">
            <template v-slot:header>
                <h4>Add member</h4>
            </template>
            <template v-slot:body>
                <label class="form-label">Phone number <small class="text-muted">(or the email of a registered user)</small></label>
                <div class="input-group mb-2">
                    <input v-model="form.login" @keyup.enter="lookup" type="text" class="form-control"
                        :class="{ 'is-invalid': errors.login }" placeholder="e.g. 0712345678" />
                    <button @click="lookup" class="btn btn-outline-secondary" type="button">Check</button>
                    <div v-if="errors.login" class="invalid-feedback">{{ errors.login[0] }}</div>
                </div>

                <div v-if="lookedUp && !preview" class="alert alert-warning py-2">
                    Enter a valid phone number such as 0712345678, or the email of someone who has registered.
                </div>
                <div v-if="preview && preview.already_member" class="alert alert-warning py-2">
                    {{ preview.phone }} already belongs to a member of this group.
                </div>
                <div v-else-if="preview && preview.registered" class="alert alert-success py-2">
                    Registered user <strong>{{ preview.name }}</strong> ({{ preview.phone }}). The membership will be linked to their account.
                </div>
                <div v-else-if="preview" class="alert alert-info py-2">
                    No account uses <strong>{{ preview.phone }}</strong>. They will be added as a member without an account,
                    and you can link an account later.
                </div>

                <template v-if="preview && !preview.already_member">
                    <BaseInput v-model="form.name" label="Full name" :error="errors.name?.[0]" />
                    <BaseSelect v-model="form.membership_type_id" label="Membership type" :options="typeOptions"
                        :error="errors.membership_type_id?.[0]" />
                    <DatePicker v-model="form.joined_at" label="Joined on" max-date="today" :error="errors.joined_at?.[0]" />
                </template>
            </template>
            <template v-slot:footer>
                <button @click="store" class="btn btn-primary" :disabled="!preview || preview.already_member">Add member</button>
            </template>
        </MediumModal>

        <MediumModal v-if="editModal" @close="closeModals">
            <template v-slot:header>
                <h4>Edit {{ editing.name }}</h4>
            </template>
            <template v-slot:body>
                <BaseInput v-model="editing.name" label="Full name" :error="errors.name?.[0]" />
                <BaseInput v-model="editing.phone_number" label="Phone number" :disabled="editing.has_account"
                    :error="errors.phone_number?.[0]" />
                <small v-if="editing.has_account" class="text-muted d-block mb-2">
                    This member has an account, so the phone number is managed by their account.
                </small>
                <BaseSelect v-model="editing.membership_type_id" label="Membership type" :options="typeOptions"
                    :error="errors.membership_type_id?.[0]" />
            </template>
            <template v-slot:footer>
                <button @click="update" class="btn btn-primary">Update</button>
            </template>
        </MediumModal>

        <MediumModal v-if="linkModal" @close="closeModals">
            <template v-slot:header>
                <h4>Link {{ linking.name }} to an account</h4>
            </template>
            <template v-slot:body>
                <p class="text-muted">
                    If this person has registered, enter the phone number or email of their account.
                    They will then be able to sign in and see this group. Check it is really them before linking.
                </p>
                <label class="form-label">Phone number or email of the account</label>
                <div class="input-group mb-2">
                    <input v-model="linkLogin" @keyup.enter="lookupLink" type="text" class="form-control"
                        :class="{ 'is-invalid': errors.login }" />
                    <button @click="lookupLink" class="btn btn-outline-secondary" type="button">Check</button>
                    <div v-if="errors.login" class="invalid-feedback">{{ errors.login[0] }}</div>
                </div>
                <div v-if="linkLooked && !linkPreview?.registered" class="alert alert-warning py-2">
                    No registered user matches that exactly.
                </div>
                <div v-else-if="linkPreview" class="alert alert-success py-2">
                    <strong>{{ linkPreview.name }}</strong> ({{ linkPreview.phone }})
                </div>
            </template>
            <template v-slot:footer>
                <button @click="link" class="btn btn-primary" :disabled="!linkPreview?.registered || linkPreview.already_member">Link account</button>
            </template>
        </MediumModal>

        <MediumModal v-if="exitModal" @close="closeModals">
            <template v-slot:header>
                <h4>Remove {{ exiting.name }}</h4>
            </template>
            <template v-slot:body>
                <p>They will leave the group and all their roles end. Their records are kept.</p>
                <BaseInput v-model="exitReason" label="Reason (optional)" :error="errors.reason?.[0]" />
            </template>
            <template v-slot:footer>
                <button @click="confirmExit" class="btn btn-danger">Remove</button>
            </template>
        </MediumModal>

        <MemberRolesModal v-if="rolesMember" :group-id="groupId" :member="rolesMember"
            :assignable-roles="assignableRoles" @close="rolesMember = null" @changed="getMembers(meta.current_page)" />
    </div>
</template>

<script>
export default {
    props: {
        groupId: { type: Number, required: true },
        canManage: { type: Boolean, default: false },
        membershipTypes: { type: Array, default: () => [] },
        assignableRoles: { type: Array, default: () => [] },
    },

    data() {
        return {
            members: [],
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            search: "",
            status: "",
            errors: {},
            addModal: false,
            form: { login: "", name: "", membership_type_id: "", joined_at: "" },
            preview: null,
            lookedUp: false,
            editModal: false,
            editing: { id: null, name: "", phone_number: "", has_account: false, membership_type_id: "" },
            linkModal: false,
            linking: { id: null, name: "" },
            linkLogin: "",
            linkPreview: null,
            linkLooked: false,
            exitModal: false,
            exiting: { id: null, name: "" },
            exitReason: "",
            rolesMember: null,
        };
    },

    computed: {
        base() {
            return `/groups/${this.groupId}/members`;
        },
        typeOptions() {
            return this.membershipTypes.map((type) => ({ value: type.id, label: type.name }));
        },
    },

    created() {
        this.getMembers();
    },

    methods: {
        getMembers(page = 1) {
            axios
                .get(this.base, { params: { page, q: this.search, status: this.status } })
                .then((response) => {
                    this.members = response.data.data;
                    this.meta = response.data.meta;

                    if (this.rolesMember) {
                        this.rolesMember = this.members.find((member) => member.id === this.rolesMember.id) ?? null;
                    }
                })
                .catch(this.handleError);
        },

        onSearch(query) {
            this.search = query;
            this.getMembers(1);
        },

        leadershipRoles(member) {
            return member.roles.filter((role) => role.name !== "Member");
        },

        openAddModal() {
            this.form = { login: "", name: "", membership_type_id: "", joined_at: new Date().toISOString().slice(0, 10) };
            this.preview = null;
            this.lookedUp = false;
            this.errors = {};
            this.addModal = true;
        },

        lookup() {
            if (!this.form.login.trim()) {
                return;
            }

            this.errors = {};

            axios
                .get(`${this.base}/lookup`, { params: { q: this.form.login } })
                .then((response) => {
                    this.preview = response.data.data;
                    this.lookedUp = true;

                    // Suggest the registered user's name, which the leader can still change.
                    if (this.preview?.registered && !this.form.name) {
                        this.form.name = this.preview.name;
                    }
                })
                .catch(this.handleError);
        },

        store() {
            axios
                .post(this.base, this.form)
                .then((response) => this.done(response.data.message))
                .catch(this.handleError);
        },

        openEditModal(member) {
            this.editing = {
                id: member.id,
                name: member.name,
                phone_number: member.phone,
                has_account: member.has_account,
                membership_type_id: member.membership_type.id,
            };
            this.errors = {};
            this.editModal = true;
        },

        update() {
            axios
                .put(`${this.base}/${this.editing.id}`, {
                    name: this.editing.name,
                    phone_number: this.editing.has_account ? null : this.editing.phone_number,
                    membership_type_id: this.editing.membership_type_id,
                })
                .then((response) => this.done(response.data.message))
                .catch(this.handleError);
        },

        openLinkModal(member) {
            this.linking = { id: member.id, name: member.name };
            this.linkLogin = "";
            this.linkPreview = null;
            this.linkLooked = false;
            this.errors = {};
            this.linkModal = true;
        },

        lookupLink() {
            if (!this.linkLogin.trim()) {
                return;
            }

            this.errors = {};

            axios
                .get(`${this.base}/lookup`, { params: { q: this.linkLogin } })
                .then((response) => {
                    this.linkPreview = response.data.data;
                    this.linkLooked = true;
                })
                .catch(this.handleError);
        },

        link() {
            axios
                .patch(`${this.base}/${this.linking.id}/link`, { login: this.linkLogin })
                .then((response) => this.done(response.data.message))
                .catch(this.handleError);
        },

        openRolesModal(member) {
            this.rolesMember = member;
        },

        setStatus(member, status, reason = null) {
            return axios
                .patch(`${this.base}/${member.id}/status`, { status, reason })
                .then((response) => this.done(response.data.message))
                .catch(this.handleError);
        },

        openExitModal(member) {
            this.exiting = { id: member.id, name: member.name };
            this.exitReason = "";
            this.errors = {};
            this.exitModal = true;
        },

        confirmExit() {
            this.setStatus(this.exiting, "exited", this.exitReason);
        },

        done(message) {
            this.closeModals();
            this.$toast.success(message);
            this.getMembers(this.meta.current_page);
        },

        closeModals() {
            this.addModal = false;
            this.editModal = false;
            this.linkModal = false;
            this.exitModal = false;
            this.errors = {};
        },

        handleError(error) {
            const data = error.response?.data;

            if (error.response?.status === 422 && data?.errors) {
                this.errors = data.errors;

                if (data.errors.status) {
                    this.closeModals();
                    this.$toast.error(data.errors.status[0]);
                }

                return;
            }

            this.$toast.error(data?.error ?? data?.message ?? "Something went wrong. Please try again.");
        },
    },
};
</script>
