<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Payment transactions" :groups="groups" :group-id="groupId" :statuses="['pending', 'completed', 'failed']"
                @group="onGroup" @search="onSearch" @status="onStatus" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Type</th><th>Member</th><th>Method</th><th class="text-end">Amount</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.transaction_number }}<div class="small text-muted">{{ item.provider_transaction_id }}</div></td>
                        <td class="text-capitalize">{{ item.type }}</td>
                        <td>{{ item.member ?? "-" }}</td>
                        <td>{{ item.payment_method }}</td>
                        <td class="text-end">{{ item.currency }} {{ money(item.amount) }}</td>
                        <td>{{ item.date }}</td>
                        <td><StatusBadge :status="item.status" /></td>
                    </tr>
                    <tr v-if="!items.length"><td colspan="8" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total" class="card-footer"><Pagination :meta="meta" @change="load" /></div>
    </div>
</template>

<script>
import paymentPage from "../../utils/paymentPage";

export default {
    mixins: [paymentPage],

    data() {
        return { endpoint: "/payment-transactions" };
    },
};
</script>
