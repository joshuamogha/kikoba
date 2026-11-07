/**
 * Shared behaviour of the payment-management pages: one group at a time, chosen with the group select,
 * a paginated list loaded from `endpoint`, and an add/edit modal posting to the same endpoint.
 */
const STORAGE_KEY = "kibubu.paymentGroup";

export function initialGroup(groups) {
    const ids = groups.map((group) => String(group.value));
    const candidates = [new URLSearchParams(window.location.search).get("group_id")];

    try {
        candidates.push(window.localStorage.getItem(STORAGE_KEY));
    } catch (error) {
        // Storage can be blocked; fall back to the first group.
    }

    return candidates.find((id) => id && ids.includes(String(id))) ?? ids[0] ?? "";
}

export function rememberGroup(groupId) {
    try {
        window.localStorage.setItem(STORAGE_KEY, groupId);
    } catch (error) {
        // Remembering the group is a convenience only.
    }
}

export default {
    props: {
        groups: { type: Array, default: () => [] },
    },

    data() {
        return {
            groupId: initialGroup(this.groups),
            items: [],
            meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            loaded: false,
            search: "",
            status: "",
            canManage: false,
            options: {},
            formModal: false,
            saving: false,
            errors: {},
            form: {},
        };
    },

    computed: {
        emptyMessage() {
            return this.loaded ? "Nothing found for this group." : "Loading...";
        },
    },

    created() {
        this.load();
    },

    methods: {
        load(page = 1) {
            if (!this.groupId) {
                this.loaded = true;
                return;
            }

            axios
                .get(this.endpoint, { params: { page, q: this.search, status: this.status, group_id: this.groupId } })
                .then((response) => {
                    this.items = response.data.data;
                    this.meta = response.data.meta;
                    this.canManage = response.data.can_manage ?? false;
                    this.options = response.data.options ?? {};
                    this.loaded = true;
                })
                .catch(this.handleError);
        },

        onGroup(groupId) {
            this.groupId = groupId;

            rememberGroup(groupId);
            this.load(1);
        },

        onSearch(query) {
            this.search = query;
            this.load(1);
        },

        onStatus(status) {
            this.status = status;
            this.load(1);
        },

        openForm(form) {
            this.form = form;
            this.errors = {};
            this.formModal = true;
        },

        closeModal() {
            this.formModal = false;
            this.errors = {};
        },

        submit(request) {
            this.saving = true;
            this.errors = {};

            request
                .then((response) => {
                    this.closeModal();
                    this.$toast.success(response.data.message);
                    this.load(this.meta.current_page);
                })
                .catch(this.handleError)
                .finally(() => (this.saving = false));
        },

        act(request) {
            request
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.load(this.meta.current_page);
                })
                .catch(this.handleError);
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },

        handleError(error) {
            const data = error.response?.data;

            if (error.response?.status === 422 && data?.errors) {
                this.errors = data.errors;
                return;
            }

            this.$toast.error(data?.error ?? data?.message ?? "Something went wrong. Please try again.");
        },
    },
};
