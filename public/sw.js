/* Perumku service worker (minimal, installable, aman dari redirect-loop).
 * Aturan: JANGAN pernah cache halaman HTML / navigasi.
 * "/" di aplikasi ini me-redirect ke /login, jadi kalau dicache
 * browser akan memutar redirect itu terus (ERR_TOO_MANY_REDIRECTS
 * yang "sembuh" sementara setelah hapus cookie).
 * Yang dicache hanya aset statis same-origin (icons, build, favicon).
 * Bump CACHE_NAME setiap ada perubahan agar cache lama dibersihkan. */
const CACHE_NAME = 'perumku-v4';
const CACHE_PREFIX = 'perumku-';
const STATIC_ASSET_PATHS = ['/favicon.ico', '/favicon.svg', '/apple-touch-icon.png', '/manifest.webmanifest'];

/* =========================================================
 * WEB PUSH — agar alarm darurat tetap sampai walau tab ditutup.
 * SW tidak bisa memutar sirene custom; ia menampilkan notifikasi
 * sistem (bunyi + getar bawaan OS) yang nempel sampai di-tap.
 * Tap → buka /resident/panic → sirene in-app menyusul bunyi.
 * ========================================================= */
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'ALARM DARURAT';
    const url = data.url || '/resident/panic';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || 'Ada warga membutuhkan bantuan segera.',
            tag: data.tag || 'panic',
            renotify: true,
            requireInteraction: true,
            silent: false,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            vibrate: [500, 200, 500, 200, 800],
            data: { url },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/resident/panic';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            const existing = wins.find((w) => w.url.includes('/resident/panic'));
            if (existing) {
                return existing.focus();
            }
            return self.clients.openWindow(url);
        })
    );
});

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
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

    // Navigasi wire:navigate memakai fetch dan dapat meminta HTML tanpa mode navigate.
    // HTML dan endpoint dinamis tidak boleh masuk cache lintas sesi.
    if (
        request.headers.get('Accept')?.includes('text/html')
        || url.pathname.startsWith('/livewire')
        || url.pathname.startsWith('/auth')
    ) {
        return;
    }

    // Cache hanya aset publik yang lokasinya memang statis.
    const isStaticAsset = url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || STATIC_ASSET_PATHS.includes(url.pathname);

    if (!isStaticAsset) {
        return;
    }

    // Aset statis saja yang di-cache (cache-first, fallback network).
    event.respondWith(
        caches.match(request).then(
            (cached) =>
                cached ||
                fetch(request).then((response) => {
                    const contentType = response.headers.get('Content-Type') || '';

                    // Jangan simpan HTML meskipun server mengembalikannya dari path aset.
                    if (
                        response.status === 200
                        && response.type === 'basic'
                        && !contentType.toLowerCase().includes('text/html')
                    ) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
        )
    );
});
