import '../css/app.css';
import './bootstrap';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

let transitionTimer;

router.on('start', () => {
    window.clearTimeout(transitionTimer);
    document.documentElement.classList.add('page-is-changing');
});

router.on('finish', () => {
    transitionTimer = window.setTimeout(() => {
        document.documentElement.classList.remove('page-is-changing');
    }, 260);
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) }).use(plugin).use(ZiggyVue).mount(el);
    },
    progress: { color: '#D4AF37' },
});
