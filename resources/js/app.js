import { createApp } from 'vue';
import axios from 'axios';
import Toast, { useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/modals.css';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const element = document.getElementById('app');

if (element) {
    const app = createApp({});

    app.use(Toast, { position: 'top-right', timeout: 4000 });

    // vue-toastification 2.x only ships the useToast() composable; expose it as this.$toast for Options API components.
    app.config.globalProperties.$toast = useToast();

    // Register every component globally under its file name (BaseInput, MediumModal, GroupTypes, ...),
    // so partials need no imports and pages can use <group-types> straight from Blade.
    const components = import.meta.glob('./components/**/*.vue', { eager: true });

    Object.entries(components).forEach(([path, module]) => {
        app.component(path.split('/').pop().replace('.vue', ''), module.default);
    });

    app.mount(element);
}
