const CACHE_NAME = 'fintrack-cache-v2';

// Solo recursos verdaderamente estáticos se cachean
const STATIC_ASSETS = [
  '/favicon.ico',
  '/manifest.json',
  '/logo-primario.png',
];

// Rutas que SIEMPRE deben ir a la red (navegación de la app)
const BYPASS_PATTERNS = [
  /^\/$/, // home
  /^\/login/,
  /^\/register/,
  /^\/verify-email/,
  /^\/dashboard/,
  /^\/password/,
  /^\/profile/,
  /^\/credit-cards/,
  /^\/purchases/,
  /^\/categories/,
  /^\/responsible-people/,
  /^\/cuts/,
  /^\/logout/,
  /^\/ai\//,
  /^\/api\//,
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames =>
      Promise.all(
        cacheNames
          .filter(name => name !== CACHE_NAME)
          .map(name => caches.delete(name))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Solo interceptar peticiones del mismo origen
  if (url.origin !== self.location.origin) {
    return; // dejar pasar sin interceptar
  }

  const pathname = url.pathname;

  // Si la ruta es de la app, siempre ir a la red
  const isBypass = BYPASS_PATTERNS.some(pattern => pattern.test(pathname));
  if (isBypass || event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() =>
        new Response('Sin conexión. Por favor recarga cuando tengas internet.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        })
      )
    );
    return;
  }

  // Para assets estáticos: cache-first (solo GET)
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(networkResponse => {
        // Solo cachear respuestas GET exitosas (Cache API no soporta POST/PATCH/DELETE)
        if (
          event.request.method === 'GET' &&
          networkResponse &&
          networkResponse.status === 200
        ) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));
        }
        return networkResponse;
      }).catch(() =>
        new Response('', { status: 503 })
      );
    })
  );
});

