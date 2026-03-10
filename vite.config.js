import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig(({ mode }) => {
    // Cargar variables de entorno de .env, .env.production, etc.
    const env = loadEnv(mode, process.cwd(), ''); // sin prefijo para acceder también a APP_URL, ASSET_URL

    const raw = env.ASSET_URL || env.APP_URL || '';
    const base = (() => {
        if (!raw) return '/build/';
        try {
            const u = new URL(raw);
            const p = (u.pathname || '/').replace(/\/+$/, '');
            if (!p) return '/build/';
            return p.endsWith('/build') ? `${p}/` : `${p}/build/`;
        } catch {
            const p = raw.replace(/\/+$/, '');
            if (!p) return '/build/';
            return p.endsWith('/build') ? `${p}/` : `${p}/build/`;
        }
    })();

    return {
        base,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
    };
});
