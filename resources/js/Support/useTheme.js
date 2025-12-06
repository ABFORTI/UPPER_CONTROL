import { ref, computed } from 'vue';

const STORAGE_KEY = 'uc-theme';
const LIGHT_COLOR = '#F5F7FA';
const DARK_COLOR = '#0B2330';

const preference = ref('system');
const appliedTheme = ref('light');
let mediaQuery;
let initialized = false;

function updateMetaTheme(color) {
    if (typeof document === 'undefined') return;
    const metas = document.querySelectorAll('meta[name="theme-color"]');
    metas.forEach((meta) => {
        if (meta.getAttribute('media')) return;
        meta.setAttribute('content', color);
    });
}

function resolveSystemTheme() {
    if (typeof window === 'undefined') return 'light';
    if (!mediaQuery) {
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    }
    return mediaQuery.matches ? 'dark' : 'light';
}

function applyTheme(mode) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    if (!root) return;

    const finalMode = mode === 'system' ? resolveSystemTheme() : mode;
    root.dataset.theme = finalMode;
    root.dataset.themePreference = mode;

    appliedTheme.value = finalMode;
    updateMetaTheme(finalMode === 'dark' ? DARK_COLOR : LIGHT_COLOR);
}

function handleSystemChange() {
    if (preference.value !== 'system') return;
    applyTheme('system');
}

export function initializeTheme() {
    if (initialized) return;
    initialized = true;

    if (typeof window === 'undefined') return;

    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    if (mediaQuery.addEventListener) {
        mediaQuery.addEventListener('change', handleSystemChange);
    } else if (mediaQuery.addListener) {
        mediaQuery.addListener(handleSystemChange);
    }

    const saved = window.localStorage.getItem(STORAGE_KEY);
    if (saved === 'light' || saved === 'dark') {
        preference.value = saved;
    } else {
        preference.value = 'system';
    }

    applyTheme(preference.value);
}

function persistPreference(mode) {
    if (typeof window === 'undefined') return;
    if (mode === 'system') {
        window.localStorage.removeItem(STORAGE_KEY);
    } else {
        window.localStorage.setItem(STORAGE_KEY, mode);
    }
}

function setTheme(mode) {
    if (!['light', 'dark', 'system'].includes(mode)) {
        mode = 'system';
    }
    preference.value = mode;
    persistPreference(mode);
    applyTheme(mode);
}

function toggleTheme() {
    const next = appliedTheme.value === 'dark' ? 'light' : 'dark';
    setTheme(next);
}

export function useTheme() {
    if (!initialized) {
        initializeTheme();
    }

    return {
        preference: computed(() => preference.value),
        currentTheme: computed(() => appliedTheme.value),
        isDark: computed(() => appliedTheme.value === 'dark'),
        setTheme,
        toggleTheme,
    };
}
