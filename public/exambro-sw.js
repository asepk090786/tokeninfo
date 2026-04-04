const EXAMBRO_CACHE = 'exambro-pwa-v2';
const OFFLINE_CACHE = [
  '/exambro',
  '/exambro?source=pwa',
  '/exambro-manifest.json',
  '/pwa/exambro-192.png',
  '/pwa/exambro-512.png',
  '/pwa/exambro-icon.svg',
  '/pwa/exambro-maskable.svg',
  '/favicon.ico'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(EXAMBRO_CACHE).then((cache) => cache.addAll(OFFLINE_CACHE))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== EXAMBRO_CACHE)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/api/exambro-')) {
    event.respondWith(
      fetch(req).catch(() =>
        new Response(JSON.stringify({
          status: 'error',
          message: 'Offline. Koneksi internet diperlukan untuk memuat status server.'
        }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' }
        })
      )
    );
    return;
  }

  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(EXAMBRO_CACHE).then((cache) => cache.put('/exambro', copy));
          return res;
        })
        .catch(() => caches.match('/exambro'))
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(EXAMBRO_CACHE).then((cache) => cache.put(req, copy));
        return res;
      });
    })
  );
});
