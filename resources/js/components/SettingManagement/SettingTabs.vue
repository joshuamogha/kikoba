<template>
    <div>
        <ul class="nav nav-tabs mb-3">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                <a href="#" class="nav-link" :class="{ active: tab.key === active }" @click.prevent="select(tab.key)">{{ tab.title }}</a>
            </li>
        </ul>

        <component :is="current.component" :key="current.key" :groups="groups" v-bind="current.props ?? {}" />
    </div>
</template>

<script>
export default {
    props: {
        tabs: { type: Array, required: true },
        groups: { type: Array, default: () => [] },
    },

    data() {
        const hash = window.location.hash.replace("#", "");

        return { active: this.tabs.some((tab) => tab.key === hash) ? hash : this.tabs[0].key };
    },

    computed: {
        current() {
            return this.tabs.find((tab) => tab.key === this.active);
        },
    },

    methods: {
        select(key) {
            this.active = key;
            window.history.replaceState(null, "", `#${key}`);
        },
    },
};
</script>
