import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import AuthenticatedLayout from './Layouts/AuthenticatedLayout.vue';
import { initializeTheme } from './Support/useTheme';
// Ziggy ya incluye las rutas automáticamente con Vite
// Solo registra el plugin:
// ...existing code...


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

initializeTheme();

// Simple gestor del splash principal (login/logout)
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
        try { if (typeof window !== 'undefined') window.__SPLASH_MODE__ = null; } catch (e) {}
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

// Loader ligero para procesos/navegación
const proc = {
    el: null,
    timer: null,
    ensure() { this.el = this.el || document.getElementById('process-loader'); return this.el; },
    show() { if (!this.ensure()) return; this.el.classList.remove('hidden'); this.el.classList.add('flex'); },
    hide() { if (!this.ensure()) return; this.el.classList.add('hidden'); this.el.classList.remove('flex'); }
}

function setProcText(msg = 'Procesando...') {
    try {
        const t = document.getElementById('process-loader-text');
        if (t) t.textContent = msg;
    } catch {}
}

function guessProcessText(method = 'get', path = '') {
    const m = String(method || 'get').toLowerCase();
    const p = String(path || '').toLowerCase();
    const isGet = m === 'get';
    if (!isGet) {
        if (m === 'delete') return 'Eliminando...';
        if (/autor|valid|aprob/.test(p)) return 'Autorizando...';
        if (/upload|subir|archivo|evidenc|adjunt|cargar/.test(p)) return 'Subiendo archivo...';
        if (/factur/.test(p)) return 'Procesando factura...';
        if (/crear|store|create|nuevo|registr/.test(p)) return 'Creando...';
        if (/actualiz|update|editar|modific/.test(p)) return 'Guardando cambios...';
        return 'Procesando...';
    }
    // GET lento (>3s)
    if (/export|reporte|descarga|download/.test(p)) return 'Preparando descarga...';
    return 'Cargando...';
}

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
    // Mostrar splash principal SOLO si se detecta modo (cookie o variable global)
    const hasSplashCookie = /(?:^|; )splash_mode=/.test(document.cookie);
    const splashMode = (typeof window !== 'undefined') ? (window.__SPLASH_MODE__ || null) : null;
    const isAuthSplash = !!(hasSplashCookie || splashMode);
    if (isAuthSplash) { splashShow(); splashTickStart(); }
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
    if (isAuthSplash) { splashTickStop(); setTimeout(splashHide, 250); }
        
    // Navegaciones internas: loader ligero
    // - GET (cambio de página): solo si tarda > 3000 ms
    // - Mutaciones (POST/PUT/PATCH/DELETE): si tarda > 160 ms
    let navTimer = null;
    const THRESHOLD_MUTATION = 160; // ms
    const THRESHOLD_GET = 3000; // ms

        function extractPath(v) {
            let raw = v?.url ?? v?.href ?? '';
            let path = '';
            try {
                const u = typeof raw === 'string' ? new URL(raw, window.location.origin) : raw;
                path = u?.pathname || '';
            } catch (_) { path = String(raw || ''); }
            return path;
        }

        function maybeShowAuthSplash(v) {
            const method = String(v?.method || '').toLowerCase();
            const path = extractPath(v);
            const cookieAuth = /(?:^|; )splash_mode=/.test(document.cookie);
            const flagAuth = !!window.__SPLASH_MODE__;
            const isAuthPath = /\/(login|logout)(\/)?$/i.test(path) || /\/(login|logout)(\b|\/)/i.test(path);
            const postAuth = (method === 'post') && isAuthPath;
            const shouldAuthSplashNow = cookieAuth || flagAuth || postAuth;

            if (postAuth && !flagAuth) {
                window.__SPLASH_MODE__ = /logout/i.test(path) ? 'logout' : 'login';
            }

            if (shouldAuthSplashNow) {
                proc.hide();
                splashShow();
                splashTickStart();
                return true;
            }
            return false;
        }

        // Mostrar splash lo antes posible en la visita (solo auth). Para mutaciones no-auth, usar loader ligero.
        document.addEventListener('inertia:visit', (e) => {
            const v = e?.detail?.visit || {};
            if (maybeShowAuthSplash(v)) return;
            const m = String(v?.method || 'get').toLowerCase();
            const path = extractPath(v);
            setProcText(guessProcessText(m, path));
            clearTimeout(navTimer);
            const wait = (m !== 'get') ? THRESHOLD_MUTATION : THRESHOLD_GET;
            navTimer = setTimeout(() => proc.show(), wait);
        });

        // Respaldo también en start por compatibilidad
        document.addEventListener('inertia:start', (e) => {
            const v = e?.detail?.visit || {};
            if (maybeShowAuthSplash(v)) return;
            const m = String(v?.method || 'get').toLowerCase();
            const path = extractPath(v);
            setProcText(guessProcessText(m, path));
            clearTimeout(navTimer);
            const wait = (m !== 'get') ? THRESHOLD_MUTATION : THRESHOLD_GET;
            navTimer = setTimeout(() => proc.show(), wait);
        });

        // Interceptar envío de formularios tradicionales (no-Inertia)
        document.addEventListener('submit', (e) => {
            try {
                const form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                const method = String(form.method || 'get').toLowerCase();
                const action = form.getAttribute('action') || '';
                const path = (() => { try { return new URL(action, window.location.origin).pathname; } catch { return action; } })();
                const isAuthPath = /(\/login|\/logout)(\b|\/)?$/i.test(path);
                // Para login/logout: splash grande
                if (isAuthPath && (method === 'post' || method === 'delete')) {
                    window.__SPLASH_MODE__ = /logout/i.test(path) ? 'logout' : 'login';
                    try { document.cookie = `splash_mode=${window.__SPLASH_MODE__}; Max-Age=15; path=/; SameSite=Lax`; } catch {}
                    splashShow();
                    splashTickStart();
                    proc.hide();
                    return;
                }
                // Para mutaciones no-auth: solo loader ligero
                if (method !== 'get') {
                    const submitter = e.submitter || null;
                    const custom = submitter?.dataset?.processText || submitter?.dataset?.loadingText || form?.dataset?.processText || form?.dataset?.loadingText;
                    setProcText(String(custom || guessProcessText(method, path)));
                    proc.show();
                }
            } catch {}
        }, true);
        document.addEventListener('inertia:finish', () => {
            const cookieAuth = /(?:^|; )splash_mode=/.test(document.cookie);
            const flagAuth = !!window.__SPLASH_MODE__;
            if (cookieAuth || flagAuth) {
                splashTickStop();
                setTimeout(splashHide, 150);
                return;
            }
            clearTimeout(navTimer);
            proc.hide();
        });

        // Interceptores Axios: mostrar loader ligero solo en mutaciones (POST/PUT/PATCH/DELETE)
        if (window.axios) {
            let pendingMut = 0;
            let reqTimer = null;
            const startProc = () => {
                clearTimeout(reqTimer);
                reqTimer = setTimeout(() => proc.show(), THRESHOLD_MUTATION);
            };
            const stopProc = () => {
                clearTimeout(reqTimer);
                proc.hide();
            };
            window.axios.interceptors.request.use((config) => {
                try {
                    const method = String(config.method || 'get').toLowerCase();
                    if (method !== 'get' && !window.__SPLASH_MODE__) {
                        const url = (() => { try { return new URL(config.url, window.location.origin).pathname; } catch { return config.url; } })();
                        setProcText(guessProcessText(method, url));
                        pendingMut++;
                        if (pendingMut === 1) startProc();
                    }
                } catch {}
                return config;
            }, (error) => {
                try {
                    stopProc();
                } catch {}
                return Promise.reject(error);
            });
            window.axios.interceptors.response.use((response) => {
                try {
                    const method = String(response?.config?.method || 'get').toLowerCase();
                    if (method !== 'get') {
                        pendingMut = Math.max(0, pendingMut - 1);
                        if (pendingMut === 0) stopProc();
                    }
                } catch {}
                return response;
            }, (error) => {
                try {
                    const method = String(error?.config?.method || 'get').toLowerCase();
                    if (method !== 'get') {
                        pendingMut = Math.max(0, pendingMut - 1);
                        if (pendingMut === 0) stopProc();
                    } else {
                        stopProc();
                    }
                } catch { stopProc(); }
                return Promise.reject(error);
            });
        }

        // Registrar Service Worker para capacidades PWA
        if (typeof window !== 'undefined' && 'serviceWorker' in navigator && !import.meta.env.DEV) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch((error) => {
                    console.error('SW registration failed', error);
                });
            });
        }
        return app;
    },
    progress: {
        color: '#4B5563',
    },
});
