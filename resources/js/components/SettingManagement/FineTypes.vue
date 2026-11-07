<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Fine types" :groups="groups" :group-id="groupId" :statuses="['active', 'inactive']"
                :can-manage="canManage" add-label="Fine type" @group="onGroup" @search="onSearch" @status="onStatus" @add="openForm(blank())" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Fine type</th><th>Calculation</th><th class="text-end">Default</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td><strong>{{ item.name }}</strong><div class="small text-muted">{{ item.code }} {{ item.description }}</div></td>
                        <td class="text-capitalize">{{ item.calculation_type }}</td>
                        <td class="text-end">{{ item.calculation_type === "percentage" ? `${item.default_amount}%` : money(item.default_amount) }}</td>
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
                    <tr v-if="!items.length"><td :colspan="canManage ? 6 : 5" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit fine type" : "Add fine type" }}</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseInput v-model="form.name" label="Name" :error="errors.name?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.code" label="Code" :error="errors.code?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.calculation_type" label="Calculation" :options="calculations" :error="errors.calculation_type?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.default_amount" type="number" min="0" step="0.01" :label="form.calculation_type === 'percentage' ? 'Default rate (%)' : 'Default amount'" :error="errors.default_amount?.[0]" /></div>
                    <div class="col-12"><BaseInput v-model="form.description" label="Description (optional)" :error="errors.description?.[0]" /></div>
                </div>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">{{ form.id ? "Update" : "Save" }}</button></template>
        </LargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return {
            endpoint: "/fine-types",
            calculations: [{ value: "fixed", label: "Fixed amount" }, { value: "percentage", label: "Percentage" }],
        };
    },

    methods: {
        blank() {
            return { id: null, name: "", code: "", description: "", default_amount: 0, calculation_type: "fixed", status: "active" };
        },

        payload(extra = {}) {
            return { ...this.form, ...extra, group_id: this.groupId };
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
