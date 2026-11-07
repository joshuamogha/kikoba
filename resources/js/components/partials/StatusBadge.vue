<template>
    <span class="badge" :class="`text-bg-${color}`">{{ label }}</span>
</template>

<script>
const colors = {
    active: "success",
    suspended: "warning",
    exited: "secondary",
    pending: "info",
    draft: "secondary",
    inactive: "secondary",
    closed: "dark",
    approved: "primary",
    completed: "success",
    partial: "warning",
    paid: "success",
    open: "success",
    submitted: "info",
    rejected: "danger",
    cancelled: "secondary",
    accepted: "success",
    declined: "danger",
    overdue: "danger",
    failed: "danger",
};

export default {
    props: {
        status: { type: String, required: true },
    },

    computed: {
        color() {
            return colors[this.status] ?? "secondary";
        },
        label() {
            return this.status.charAt(0).toUpperCase() + this.status.slice(1);
        },
    },
};
</script>
