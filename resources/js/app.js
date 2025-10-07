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

// Simple gestor del splash
function splashShow() {
    const el = document.getElementById('app-splash');
    if (el) {
        el.style.display = 'block';
        requestAnimationFrame(() => { el.style.opacity = 1; el.style.pointerEvents = 'auto'; });
    }
}
function splashHide() {
    const el = document.getElementById('app-splash');
    if (!el) return;
    el.style.transition = 'opacity .35s ease';
    el.style.opacity = 0;
    setTimeout(() => {
        el.style.display = 'none';
        // borrar cookie splash_mode para evitar reaparición en refresh
        try { document.cookie = 'splash_mode=; Max-Age=0; path=/;'; } catch (e) {}
    }, 380);
}
function splashProgress(pct = 100) {
    const bar = document.getElementById('app-splash-bar');
    if (bar) bar.style.width = `${Math.max(0, Math.min(100, pct))}%`;
}
// Arranca el splash progresivo básico
let splashTimer = null;
function splashTickStart() {
    clearInterval(splashTimer);
    let w = 12;
    splashProgress(w);
    splashTimer = setInterval(() => {
        // crece suavemente sin llegar al 100 hasta montar
        w = Math.min(92, w + Math.random() * 8);
        splashProgress(w);
    }, 400);
}
function splashTickStop() { clearInterval(splashTimer); splashProgress(100); }

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

    return component;
    },
    setup({ el, App, props, plugin }) {
        // Mostrar SIEMPRE el splash como pantalla de carga inicial
        splashShow();
        splashTickStart();
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
        
        // Eventos de progreso de Inertia
        document.addEventListener('inertia:progress', (e) => {
            const p = e?.detail?.progress?.percentage;
            if (typeof p === 'number') splashProgress(10 + (p * 0.9));
        });

        // Cuando esté montado, ocultar splash
        app.mount(el);
        splashTickStop();
        setTimeout(splashHide, 250);
        
        // Mostrar splash en cada navegación
        document.addEventListener('inertia:start', () => {
            splashShow();
            splashTickStart();
        });
        document.addEventListener('inertia:finish', () => {
            splashTickStop();
            setTimeout(splashHide, 150);
        });
        return app;
    },
    progress: {
        color: '#4B5563',
    },
});
