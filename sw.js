const STATIC_CACHE = 'expense-tracker-static-v1';
const DYNAMIC_CACHE = 'expense-tracker-dynamic-v1';

const STATIC_ASSETS = [
  './',
  './auth/login.php',
  './assets/css/style.css',
  './assets/js/app.js',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './offline.html'
];

const isStaticAsset = (url) => {
  return (
    url.pathname.includes('/assets/') ||
    url.pathname.endsWith('/offline.html') ||
    url.pathname.endsWith('/manifest.json')
  );
};

const isDynamicRequest = (url) => {
  return (
    url.pathname.endsWith('.php') ||
    url.pathname.includes('/api/')
  );
};

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(event.request.url);
  const isSameOrigin = requestUrl.origin === self.location.origin;

  if (!isSameOrigin) {
    return;
  }

  // Cache-first for static files.
  if (isStaticAsset(requestUrl)) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        if (cached) {
          return cached;
        }

        return fetch(event.request).then((response) => {
          if (response.ok) {
            const responseClone = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(event.request, responseClone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first for dynamic PHP/API and navigation.
  if (isDynamicRequest(requestUrl) || event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.ok) {
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE).then((cache) => cache.put(event.request, responseClone));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(event.request);
          if (cached) {
            return cached;
          }

          const loginFallbackUrl = new URL('./auth/login.php', self.registration.scope).toString();
          const loginFallback = await caches.match(loginFallbackUrl);
          if (event.request.mode === 'navigate' && loginFallback) {
            return loginFallback;
          }

          const offlineFallbackUrl = new URL('./offline.html', self.registration.scope).toString();
          const offlineFallback = await caches.match(offlineFallbackUrl);
          if (offlineFallback) {
            return offlineFallback;
          }

          return new Response('Offline', { status: 503, statusText: 'Offline' });
        })
    );
  }
});
