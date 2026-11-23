<template>
    <div ref="chart"></div>
</template>

<script>
/**
 * One ApexCharts chart (the library is loaded on every dashboard page). Pass the ApexCharts options; the chart redraws when they change.
 */
export default {
    props: {
        options: { type: Object, required: true },
    },

    mounted() {
        this.instance = new window.ApexCharts(this.$refs.chart, this.options);
        this.instance.render();
    },

    watch: {
        options: {
            deep: true,
            handler(options) {
                this.instance?.updateOptions(options);
            },
        },
    },

    beforeUnmount() {
        this.instance?.destroy();
    },
};
</script>
