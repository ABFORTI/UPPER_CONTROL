// Este Service Worker se auto-desregistra e invalida todos los caches.
// Fue necesario porque versiones anteriores cacheaban HTML y scripts de forma
// agresiva, impidiendo que el navegador cargara bundles actualizados.
// Para re-habilitar PWA en el futuro, reemplazar este archivo con uno nuevo
// y actualizar CACHE_VERSION en el SW.
'use strict';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.map((k) => caches.delete(k))))
            .then(() => self.registration.unregister())
            .then(() => self.clients.matchAll())
            .then((clients) => clients.forEach((c) => c.navigate(c.url)))
    );
});

// No interceptar ninguna request - dejar que el browser las maneje normalmente.

const APP_SHELL = [
  '/',
  '/offline.html',
  '/manifest.json',
  '/manifest.json?v=2',
  '/img/upper_control192.png',
  '/img/upper_control512.png',
  '/img/upper_control1024.png',
  '/img/logo.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION)
      .then((cache) => Promise.allSettled(APP_SHELL.map((url) => cache.add(url))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((key) => {
        if (key !== CACHE_VERSION) {
          return caches.delete(key);
        }
        return null;
      }))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(request.url);
  const isSameOrigin = requestUrl.origin === self.location.origin;

  // Las navegaciones (HTML) NUNCA se sirven desde caché:
  // el HTML de Laravel lleva CSRF token dinámico y debe venir siempre del servidor.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .catch(() => caches.match('/offline.html').then((r) => r || new Response('Offline', { status: 503 })))
    );
    return;
  }

  if (!isSameOrigin) {
    return;
  }

  // Assets con hash en la URL (Vite: app-AbCdEf.js, app-AbCd.css):
  // son inmutables por nombre → cache-first.
  const isHashedAsset = /\/assets\/[^/]+-[A-Za-z0-9_-]{8,}\.(js|css)$/.test(requestUrl.pathname);
  if (isHashedAsset) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          const copy = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
          return response;
        });
      })
    );
    return;
  }

  // Imágenes y fuentes: cache-first pero solo de same-origin.
  const staticTypes = ['image', 'font'];
  if (staticTypes.includes(request.destination)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          const copy = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
          return response;
        });
      })
    );
    return;
  }

  // Todo lo demás: network-first, sin caché.
  event.respondWith(fetch(request).catch(() => caches.match(request)));
});
