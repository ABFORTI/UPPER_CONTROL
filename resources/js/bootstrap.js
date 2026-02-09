import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF para rutas web (sesión)
const tokenEl = document.head?.querySelector('meta[name="csrf-token"]');
const csrf = tokenEl?.content;
if (csrf) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

// Cookies/XSRF (por compatibilidad)
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
