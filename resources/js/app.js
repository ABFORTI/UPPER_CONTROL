import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import AuthenticatedLayout from './Layouts/AuthenticatedLayout.vue';
// Ziggy ya incluye las rutas automáticamente con Vite
// Solo registra el plugin:
// ...existing code...


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const mod = await resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        );

        // Obtén el componente real (default export del módulo)
        const component = mod?.default ?? mod;
        // Si no tiene layout definido, aplica el AuthenticatedLayout solo a páginas autenticadas.
        // Excluir vistas de autenticación y páginas invitadas (Auth/*, Errors/*, Welcome, etc.).
        const noLayoutPatterns = [/^Auth\//, /^Auth\//, /^Errors\//, /^Welcome$/];
        const shouldApplyDefaultLayout = !noLayoutPatterns.some((rx) => rx.test(name));
        if (!component.layout && shouldApplyDefaultLayout) {
            component.layout = AuthenticatedLayout;
        }

        return mod;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
