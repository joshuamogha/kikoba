<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Loan guarantors" :groups="groups" :group-id="groupId" :statuses="['pending', 'accepted', 'declined']"
                :can-manage="canManage || !!options.applications?.length" add-label="Guarantor" @group="onGroup" @search="onSearch" @status="onStatus"
                @add="openForm({ loan_application_id: '', group_member_id: '', guaranteed_amount: '' })" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Application</th><th>Borrower</th><th>Guarantor</th><th class="text-end">Guaranteed</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.application_number }}<div class="small text-muted">Requested {{ money(item.requested_amount) }}</div></td>
                        <td>{{ item.borrower }}</td>
                        <td>{{ item.guarantor.name }}<div class="small text-muted">{{ item.guarantor.member_number }}</div></td>
                        <td class="text-end">{{ money(item.guaranteed_amount) }}</td>
                        <td><StatusBadge :status="item.status" /><div v-if="item.remarks" class="small text-muted">{{ item.remarks }}</div></td>
                        <td>
                            <div v-if="item.status === 'pending' && item.application_status === 'submitted' && (item.is_mine || canManage)" class="btn-group btn-group-sm">
                                <button @click="respond(item, 'accepted')" class="btn btn-success" title="Accept"><i class="bi bi-check-lg"></i></button>
                                <button @click="respond(item, 'declined')" class="btn btn-danger" title="Decline"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td colspan="7" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>

        <LargeModal v-if="formModal" @close="closeModal">
            <template v-slot:header><h4>Add guarantor</h4></template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6"><BaseSelect v-model="form.loan_application_id" label="Application" :options="options.applications" :error="errors.loan_application_id?.[0]" /></div>
                    <div class="col-md-6"><MemberSearchable v-model="form.group_member_id" label="Guarantor" :options="options.members" :error="errors.group_member_id?.[0]" /></div>
                    <div class="col-md-6"><BaseInput v-model="form.guaranteed_amount" type="number" min="0" step="0.01" label="Amount guaranteed" :error="errors.guaranteed_amount?.[0]" /></div>
                </div>
            </template>
            <template v-slot:footer><button @click="save" class="btn btn-primary" :disabled="saving">Save</button></template>
        </LargeModal>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/loan-guarantors" };
    },

    methods: {
        save() {
            this.submit(axios.post(this.endpoint, { ...this.form, group_id: this.groupId }));
        },

        respond(item, decision) {
            this.act(axios.patch(`${this.endpoint}/${item.id}/respond`, { decision }));
        },
    },
};
</script>
