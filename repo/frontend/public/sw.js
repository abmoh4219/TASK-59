/* Workforce & Operations Hub — minimal service worker.
 * Caches static assets and shell for offline navigation.
 * Network-first for /api/* GETs with cache fallback.
 * Write requests are not handled here — see src/api/offlineQueue.ts. */
const CACHE = 'wfops-shell-v1';
const SHELL = ['/', '/index.html', '/vite.svg'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).catch(() => null));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
    ),
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => null);
          return res;
        })
        .catch(() => caches.match(req).then((r) => r || new Response(JSON.stringify({ offline: true }), { status: 503, headers: { 'Content-Type': 'application/json' } }))),
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => cached || fetch(req).catch(() => caches.match('/index.html'))),
  );
});
