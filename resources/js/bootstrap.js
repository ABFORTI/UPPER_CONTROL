import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

//  CSRF: NO inyectar X-CSRF-TOKEN manualmente. 
// Raz�n: el meta tag csrf-token del DOM NO se actualiza en navegaciones Inertia (SPA).
// Despu�s de logout  session-regenerate  el token del meta tag queda OBSOLETO.
// Si lo mandamos en el header, Laravel lo usa primero y falla con 419,
// aunque la cookie XSRF-TOKEN sea correcta.
//
// Mecanismo correcto:
// - axios lee autom�ticamente la cookie `XSRF-TOKEN` y la env�a como `X-XSRF-TOKEN`
// - Laravel siempre renueva la cookie XSRF-TOKEN en sus respuestas
// - Esto es din�mico y funciona correctamente despu�s de logout/session-regenerate

function __ucGetPath(rawUrl) {
try {
return new URL(String(rawUrl || ''), window.location.origin).pathname;
} catch {
return String(rawUrl || '');
}
}

function __ucIsLogoutPath(path) {
return /(^|\/)logout(\b|\/)?$/i.test(String(path || ''));
}

function __ucIsExplicitLogoutAllowed() {
try {
const now = Date.now();
const last = Number(window.__lastExplicitLogoutAt || 0);
const diff = now - last;
const allowed = diff <= 2000;
console.log(`[upper-control/bootstrap] isLogoutAllowed()  diff=${diff}ms  ${allowed ? 'PERMITIDO' : 'BLOQUEADO'}`);
return allowed;
} catch {
return false;
}
}

function __ucBlockLogout(source, details = {}) {
const err = new Error(`[upper-control] LOGOUT BLOQUEADO (${source})  intento automatico/no explicito cancelado.`);
console.error(err.message, details);
console.error(err.stack);
}

//  Guard axios: interceptor oficial, captura TODOS los metodos 
if (typeof window !== 'undefined' && window.axios && !window.__axiosLogoutGuardInstalled) {
window.__axiosLogoutGuardInstalled = true;
window.axios.interceptors.request.use((config) => {
let blocked = false;
try {
const method = String(config?.method || 'get').toLowerCase();
if (method !== 'get') {
const path = __ucGetPath(config?.url || '');
if (__ucIsLogoutPath(path) && !__ucIsExplicitLogoutAllowed()) {
blocked = true;
}
}
} catch (e) {
console.error('[upper-control] Error en axios logout guard:', e);
}
if (blocked) {
__ucBlockLogout('axios', { url: config?.url, method: config?.method });
return Promise.reject(Object.assign(new Error('Logout bloqueado automaticamente'), { __ucBlocked: true }));
}
return config;
}, undefined);
}

//  Guard fetch() 
if (typeof window !== 'undefined' && typeof window.fetch === 'function' && !window.__csrfFetchPatched) {
window.__csrfFetchPatched = true;
const originalFetch = window.fetch.bind(window);
window.fetch = (input, init = {}) => {
let blocked = false;
try {
const method = String(init?.method || 'GET').toUpperCase();
const isMutation = !['GET', 'HEAD', 'OPTIONS'].includes(method);
if (isMutation) {
const rawUrl = (typeof input === 'string') ? input : (input?.url || '');
const urlObj = new URL(String(rawUrl || ''), window.location.origin);
if (urlObj.origin === window.location.origin) {
if (__ucIsLogoutPath(urlObj.pathname) && !__ucIsExplicitLogoutAllowed()) {
blocked = true;
}
}
}
} catch (e) {
console.error('[upper-control] Error en fetch guard:', e);
}
if (blocked) {
const rawUrl = (typeof input === 'string') ? input : (input?.url || '');
__ucBlockLogout('fetch', { url: rawUrl });
return Promise.reject(Object.assign(new Error('Logout bloqueado automaticamente'), { __ucBlocked: true }));
}
return originalFetch(input, init);
};
}

//  Guard XHR directo 
if (typeof window !== 'undefined' && typeof window.XMLHttpRequest === 'function' && !window.__xhrLogoutGuardInstalled) {
window.__xhrLogoutGuardInstalled = true;
const OriginalOpen = window.XMLHttpRequest.prototype.open;
const OriginalSend = window.XMLHttpRequest.prototype.send;

window.XMLHttpRequest.prototype.open = function (method, url, ...rest) {
this.__uc_method = method;
this.__uc_url    = url;
this.__uc_block  = false;
try {
const m = String(method || 'GET').toUpperCase();
if (!['GET', 'HEAD', 'OPTIONS'].includes(m)) {
const path = __ucGetPath(url || '');
if (__ucIsLogoutPath(path) && !__ucIsExplicitLogoutAllowed()) {
this.__uc_block = true;
}
}
} catch (e) {
console.error('[upper-control] Error en XHR open guard:', e);
}
return OriginalOpen.call(this, method, url, ...rest);
};

window.XMLHttpRequest.prototype.send = function (body) {
if (this.__uc_block) {
__ucBlockLogout('XMLHttpRequest', { url: this.__uc_url, method: this.__uc_method });
return;
}
return OriginalSend.call(this, body);
};
}

//  Configuracion XSRF para axios 
// axios lee la cookie XSRF-TOKEN (siempre fresca) y la envia como X-XSRF-TOKEN.
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
