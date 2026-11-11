// Keeps the field-entry page available without signal. Only that page is cached, never beneficiary records.
const CACHE = 'field-entry-v1';
const PAGE = '/beneficiaries/field';

self.addEventListener('install', (event) => { self.skipWaiting(); });
self.addEventListener('activate', (event) => { event.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.pathname !== PAGE) { return; }
    event.respondWith(
        fetch(event.request).then((response) => {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put(PAGE, copy));
            return response;
        }).catch(() => caches.match(PAGE))
    );
});
