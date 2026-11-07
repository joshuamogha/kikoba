<template>
    <div class="card">
        <div class="card-header">
            <PaymentToolbar title="Fine payments" :groups="groups" :group-id="groupId" @group="onGroup" @search="onSearch" @status="onStatus" />
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Number</th><th>Fine</th><th>Member</th><th class="text-end">Amount</th><th>Method</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>{{ (meta.from || 1) + index }}</td>
                        <td>{{ item.payment_number }}<div class="small text-muted">{{ item.external_reference }}</div></td>
                        <td>{{ item.fine_number }}<div class="small text-muted">{{ item.fine_type }}</div></td>
                        <td>{{ item.member.name }}<div class="small text-muted">{{ item.member.member_number }}</div></td>
                        <td class="text-end">{{ money(item.amount) }}</td>
                        <td>{{ item.payment_method }}</td>
                        <td>{{ item.payment_date }}</td>
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
        return { endpoint: "/fine-payments" };
    },
};
</script>
