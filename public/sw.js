/* Perumku service worker (minimal, installable).
 * Network-first agar halaman Livewire selalu segar,
 * fallback ke cache saat offline. Bump CACHE_NAME
 * setiap ada perubahan besar agar cache lama dibersihkan. */
const CACHE_NAME = 'perumku-v1';
const OFFLINE_URLS = ['/', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(OFFLINE_URLS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    // Hanya tangani GET http(s); biarkan POST Livewire lewat apa adanya.
    if (request.method !== 'GET' || ! request.url.startsWith('http')) {
        return;
    }
    event.respondWith(
        fetch(request)
            .then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                return response;
            })
            .catch(() => caches.match(request).then((cached) => cached || caches.match('/')))
    );
});
