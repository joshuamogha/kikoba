<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Group funds" :groups="groups" :group-id="groupId" :statuses="['active', 'inactive']"
                :can-manage="canManage" add-label="Fund" @group="onGroup" @search="onSearch" @status="onStatus" @add="openForm(blank())" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Fund</th><th>Type</th><th>Frequency</th><th class="text-end">Minimum</th><th>Rules</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td><strong>{{ item.name }}</strong><div class="small text-muted">{{ item.code }}</div></td>
                        <td>{{ item.fund_type }}</td>
                        <td>{{ item.frequency }}</td>
                        <td class="text-end">{{ money(item.minimum_contribution) }}</td>
                        <td class="small">
                            <span v-if="item.is_mandatory" class="badge text-bg-info me-1">Mandatory</span>
                            <span v-if="item.is_distributable" class="badge text-bg-info me-1">Distributable</span>
                            <span v-if="item.is_loanable" class="badge text-bg-info">Loanable</span>
                        </td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td v-if="canManage">
                            <div class="btn-group btn-group-sm">
                                <button @click="toggle(item)" class="btn" :class="item.status === 'active' ? 'btn-warning' : 'btn-success'" :title="item.status === 'active' ? 'Deactivate' : 'Activate'">
                                    <i class="bi" :class="item.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                                </button>
                                <button @click="openForm({ ...item, description: item.description ?? '' })" class="btn btn-secondary" title="Edit"><i class="bi bi-pencil"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 8 : 7" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit fund" : "Add fund" }}</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseInput v-model="form.name" label="Name" :error="errors.name?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.code" label="Code" :error="errors.code?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.description" label="Description (optional)" :error="errors.description?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.fund_type_id" label="Fund type" :options="options.fund_types" :error="errors.fund_type_id?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.contribution_frequency_id" label="Contribution frequency" :options="options.frequencies" :error="errors.contribution_frequency_id?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.minimum_contribution" type="number" min="0" step="0.01" label="Minimum contribution" :error="errors.minimum_contribution?.[0]" /></div>
                    <div class="col-md-6"><div class="form-check form-switch"><input id="mandatory" v-model="form.is_mandatory" class="form-check-input" type="checkbox" /><label class="form-check-label" for="mandatory">Mandatory</label></div></div>
                    <div class="col-md-6"><div class="form-check form-switch"><input id="distributable" v-model="form.is_distributable" class="form-check-input" type="checkbox" /><label class="form-check-label" for="distributable">Distributed at share out</label></div></div>
                    <div class="col-md-6"><div class="form-check form-switch"><input id="loanable" v-model="form.is_loanable" class="form-check-input" type="checkbox" /><label class="form-check-label" for="loanable">Available for loans</label></div></div>
                </div>
            </template>
            <template v-slot:footer>
                <button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ? "Update" : "Save" }}</button>
            </template>
        </LargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/group-funds" };
    },

    methods: {
        blank() {
            return { id: null, name: "", code: "", description: "", fund_type_id: "", contribution_frequency_id: "", minimum_contribution: 0, is_mandatory: false, is_distributable: false, is_loanable: false, status: "active" };
        },

        payload(extra = {}) {
            return { ...this.form, ...extra, group_id: this.groupId, is_mandatory: !!this.form.is_mandatory, is_distributable: !!this.form.is_distributable, is_loanable: !!this.form.is_loanable };
        },

        save() {
            this.submit(this.form.id ? axios.put(`${this.endpoint}/${this.form.id}`, this.payload()) : axios.post(this.endpoint, this.payload()));
        },

        toggle(item) {
            this.form = { ...item, description: item.description ?? "" };
            this.act(axios.put(`${this.endpoint}/${item.id}`, this.payload({ status: item.status === "active" ? "inactive" : "active" })));
        },
    },
};
</script>
