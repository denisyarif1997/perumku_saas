/* Perumku service worker (minimal, installable, aman dari redirect-loop).
 * Aturan: JANGAN pernah cache halaman HTML / navigasi.
 * "/" di aplikasi ini me-redirect ke /login, jadi kalau dicache
 * browser akan memutar redirect itu terus (ERR_TOO_MANY_REDIRECTS
 * yang "sembuh" sementara setelah hapus cookie).
 * Yang dicache hanya aset statis same-origin (icons, build, favicon).
 * Bump CACHE_NAME setiap ada perubahan agar cache lama dibersihkan. */
const CACHE_NAME = 'perumku-v2';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Hanya GET same-origin. Biarkan POST Livewire + cross-origin lewat.
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Navigasi halaman (HTML): network-only, JANGAN dicache.
    // Ini mencegah halaman redirect (/ -> /login) tersimpan di cache.
    if (request.mode === 'navigate') {
        return;
    }

    // Jangan cache endpoint dinamis Livewire / auth.
    if (url.pathname.startsWith('/livewire') || url.pathname.startsWith('/auth')) {
        return;
    }

    // Aset statis saja yang di-cache (cache-first, fallback network).
    event.respondWith(
        caches.match(request).then(
            (cached) =>
                cached ||
                fetch(request).then((response) => {
                    // Hanya simpan respons OK biasa, bukan redirect/error.
                    if (response && response.status === 200 && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
        )
    );
});
