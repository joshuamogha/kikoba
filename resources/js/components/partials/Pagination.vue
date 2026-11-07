<template>
    <div class="d-flex flex-column align-items-center gap-2">
        <Bootstrap5Pagination :data="paginator" :limit="3" :show-disabled="true" align="center" class="mb-0"
            @pagination-change-page="$emit('change', $event)" />
        <small class="text-muted">Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }}</small>
    </div>
</template>

<script>
import { Bootstrap5Pagination } from "laravel-vue-pagination";

export default {
    components: { Bootstrap5Pagination },

    props: {
        meta: {
            type: Object,
            required: true,
        },
    },

    emits: ["change"],

    computed: {
        /**
         * The shape laravel-vue-pagination reads from a Laravel resource response: the meta plus first/last/prev/next links.
         */
        paginator() {
            return {
                meta: this.meta,
                links: {
                    first: this.meta.current_page > 1 ? "#" : null,
                    last: this.meta.current_page < this.meta.last_page ? "#" : null,
                    prev: this.meta.current_page > 1 ? "#" : null,
                    next: this.meta.current_page < this.meta.last_page ? "#" : null,
                },
            };
        },
    },
};
</script>
