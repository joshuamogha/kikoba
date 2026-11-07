<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Financial cycles" :groups="groups" :group-id="groupId" :statuses="['draft', 'open', 'closed']"
                :can-manage="canManage" add-label="Cycle" @group="onGroup" @search="onSearch" @status="onStatus"
                @add="openForm({ id: null, name: '', contribution_frequency_id: '', start_date: '', end_date: '' })" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Frequency</th><th>Start</th><th>End</th><th>Status</th><th v-if="canManage">Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td>{{ item.cycle_number }}</td>
                        <td>{{ item.name }}</td>
                        <td>{{ item.frequency }}</td>
                        <td>{{ item.start_date }}</td>
                        <td>{{ item.end_date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                        <td v-if="canManage">
                            <div class="btn-group btn-group-sm">
                                <button v-if="item.status === 'draft'" @click="change(item, 'open')" class="btn btn-success" title="Open"><i class="bi bi-play-circle"></i></button>
                                <button v-if="item.status === 'open'" @click="change(item, 'close')" class="btn btn-warning" title="Close"><i class="bi bi-lock"></i></button>
                                <button v-if="item.status !== 'closed'" @click="openForm({ ...item })" class="btn btn-secondary" title="Edit"><i class="bi bi-pencil"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="canManage ? 7 : 6" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>{{ form.id ? "Edit cycle" : "Add cycle" }}</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseInput v-model="form.name" label="Name" :error="errors.name?.[0]" /></div>
                    <div class="col-md-6"><BaseSelect v-model="form.contribution_frequency_id" label="Contribution frequency" :options="options.frequencies" :error="errors.contribution_frequency_id?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.start_date" label="Start date" :error="errors.start_date?.[0]" /></div>
                    <div class="col-md-6"><DatePicker v-model="form.end_date" label="End date" :error="errors.end_date?.[0]" /></div>
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
        return { endpoint: "/financial-cycles" };
    },

    methods: {
        change(item, action) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/${action}`));
        },

        save() {
            const payload = { ...this.form, group_id: this.groupId };

            this.submit(this.form.id ? axios.put(`${this.endpoint}/${this.form.id}`, payload) : axios.post(this.endpoint, payload));
        },
    },
};
</script>
