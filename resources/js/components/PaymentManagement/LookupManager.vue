<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar :title="title" :groups="groups" :group-id="groupId" :statuses="['active', 'inactive']"
                :can-manage="canManage" add-label="Add" @group="onGroup" @search="onSearch" @status="onStatus"
                @add="openForm({ id: null, name: '', code: '', interval_days: 30, description: '', is_active: true })" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th v-if="hasCode">Code</th>
                        <th v-if="hasInterval">Every</th>
                        <th>Status</th>
                        <th v-if="canManage">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>
                            <strong>{{ item.name }}</strong>
                            <div class="small text-muted">{{ item.description }}</div>
                        </td>
                        <td v-if="hasCode">{{ item.code }}</td>
                        <td v-if="hasInterval">{{ item.interval_days }} days</td>
                        <td><StatusBadge :status="item.is_active ? 'active' : 'inactive'" /></td>
                        <td v-if="canManage">
                            <div class="btn-group btn-group-sm">
                                <button @click="toggle(item)" class="btn" :class="item.is_active ? 'btn-warning' : 'btn-success'"
                                    :title="item.is_active ? 'Deactivate' : 'Activate'">
                                    <i class="bi" :class="item.is_active ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                                </button>
                                <button @click="openForm({ ...item, description: item.description ?? '' })" class="btn btn-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td :colspan="4 + (hasCode ? 1 : 0) + (hasInterval ? 1 : 0)" class="text-center text-muted py-4">{{ emptyMessage }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <MediumModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit" : "Add" }} {{ title.toLowerCase() }}</h4></template>
            <template v-slot:body>
                <BaseInput v-model="form.name" label="Name" :error="errors.name?.[0]" />
                <BaseInput v-if="hasCode" v-model="form.code" label="Code" :disabled="lockCode && !!form.id" :error="errors.code?.[0]" />
                <BaseInput v-if="hasInterval" v-model="form.interval_days" type="number" min="1" max="365" label="Days between payments" :error="errors.interval_days?.[0]" />
                <BaseInput v-model="form.description" label="Description (optional)" :error="errors.description?.[0]" />
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ? "Update" : "Save" }}</button>
            </template>
        </MediumModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    props: {
        title: { type: String, required: true },
        endpoint: { type: String, required: true },
        hasCode: { type: Boolean, default: false },
        lockCode: { type: Boolean, default: false },
        hasInterval: { type: Boolean, default: false },
    },

    methods: {
        save() {
            const payload = { ...this.form, group_id: this.groupId };

            this.submit(this.form.id ? axios.put(`${this.endpoint}/${this.form.id}`, payload) : axios.post(this.endpoint, payload));
        },

        toggle(item) {
            this.act(axios.put(`${this.endpoint}/${item.id}`, { ...item, description: item.description ?? "", is_active: !item.is_active, group_id: this.groupId }));
        },
    },
};
</script>
