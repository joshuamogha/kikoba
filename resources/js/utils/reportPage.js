import { initialGroup, rememberGroup } from "./paymentPage";

/**
 * Shared behaviour of the report pages: a group select, a financial cycle select (open cycles first), optional member / fund /
 * status filters, the report loaded as JSON from `endpoint`, and Print and CSV buttons that use the same filters.
 */
export default {
    props: {
        groups: { type: Array, default: () => [] },
    },

    data() {
        return {
            groupId: initialGroup(this.groups),
            cycleId: "",
            memberId: "",
            fundId: "",
            status: "",
            report: null,
            cycles: [],
            loaded: false,
        };
    },

    computed: {
        queryParams() {
            return { group_id: this.groupId, financial_cycle_id: this.cycleId || undefined, group_member_id: this.memberId || undefined, group_fund_id: this.fundId || undefined, status: this.status || undefined };
        },

        csvUrl() {
            const query = new URLSearchParams({ ...Object.fromEntries(Object.entries(this.queryParams).filter(([, value]) => value !== undefined && value !== "")), format: "csv" });

            return `${this.endpoint}?${query.toString()}`;
        },

        currency() {
            return this.report?.group?.currency ?? "";
        },
    },

    created() {
        this.load();
    },

    methods: {
        load() {
            if (!this.groupId) {
                this.loaded = true;

                return;
            }

            axios
                .get(this.endpoint, { params: this.queryParams })
                .then((response) => {
                    this.report = response.data.data;
                    this.cycles = response.data.cycles ?? [];
                    this.cycleId = this.report?.cycle?.id ?? this.cycleId;
                    this.afterLoad(response.data);
                    this.loaded = true;
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        afterLoad() {},

        onGroup(groupId) {
            this.groupId = groupId;
            this.cycleId = "";
            this.memberId = "";
            this.fundId = "";
            rememberGroup(groupId);
            this.load();
        },

        onCycle(cycleId) {
            this.cycleId = cycleId;
            this.load();
        },

        onMember(memberId) {
            this.memberId = memberId;
            this.load();
        },

        onFund(fundId) {
            this.fundId = fundId;
            this.load();
        },

        onStatus(status) {
            this.status = status;
            this.load();
        },

        print() {
            window.print();
        },

        money(value) {
            return new Intl.NumberFormat("en-TZ", { maximumFractionDigits: 2 }).format(value ?? 0);
        },
    },
};
