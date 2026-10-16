import { createIcons, icons } from 'lucide';
import './panic-alert';

/**
 * Render semua ikon Lucide (<i data-lucide="...">) pada dokumen.
 * Aman dipanggil berulang kali: elemen yang sudah dikonversi ke SVG
 * akan dibuat ulang dengan hasil yang identik (tidak ada duplikasi ikon).
 */
function renderIcons() {
    try {
        createIcons({ icons, attrs: { 'stroke-width': 1.75 } });
    } catch (error) {
        console.error('[lucide] gagal merender ikon:', error);
    }
}

// Render pertama setelah DOM siap.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderIcons, { once: true });
} else {
    renderIcons();
}

// Dirender ulang setiap kali halaman dimuat via wire:navigate
// (event ini juga terpicu pada load pertama di Livewire 3).
document.addEventListener('livewire:navigated', renderIcons);

// Render ulang ikon setelah setiap pembaruan DOM Livewire
// (baris tabel baru, form edit, hasil filter, dsb).
document.addEventListener('livewire:init', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => renderIcons());
    });
});

/* =========================================================
 * DARK MODE TOGGLE
 * Preferensi tersimpan di localStorage key 'theme'.
 * Jika belum pernah dipilih, ikuti preferensi sistem (prefers-color-scheme).
 * ========================================================= */
const THEME_KEY = 'theme';

function applyTheme(dark) {
    document.documentElement.classList.toggle('dark', dark);
    document.querySelectorAll('[data-theme-toggle]')?.forEach((btn) => {
        btn.setAttribute('aria-label', dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
    });
}

function initTheme() {
    const stored = localStorage.getItem(THEME_KEY);
    const dark = stored ? stored === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(dark);
    return dark;
}

function toggleTheme() {
    const dark = !document.documentElement.classList.contains('dark');
    localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light');
    applyTheme(dark);
}

// Toggle pertama saat skrip dimuat (sebelum Livewire siap).
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme, { once: true });
} else {
    initTheme();
}

// Delegasi klik: tombol [data-theme-toggle] bekerja di mana pun (termasuk hasil render Livewire).
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) {
        toggleTheme();
    }
});

// Pastikan tema tetap konsisten setelah navigasi wire:navigate
// dan ikon toggle ikut dirender ulang.
document.addEventListener('livewire:navigated', () => {
    const stored = localStorage.getItem(THEME_KEY);
    applyTheme(stored ? stored === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches);
});

// Sinkron antar tab
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (!localStorage.getItem(THEME_KEY)) applyTheme(e.matches);
});

/* =========================================================
 * PWA: daftarkan service worker agar syarat "Install App"
 * Chrome (manifest + SW + icon) terpenuhi.
 * ========================================================= */
/**
 * WEB PUSH SUBSCRIBE — daftarkan perangkat agar alarm darurat
 * tetap sampai walau tab ditutup (via Service Worker + VAPID).
 */
function pushBase64ToBytes(base64) {
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        bytes[i] = raw.charCodeAt(i);
    }
    return bytes;
}

async function pushEnsureSubscription() {
    try {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return;
        }
        if (!('Notification' in window) || Notification.permission === 'denied') {
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) {
            return;
        }
        const keyRes = await fetch('/push/vapid-key', { headers: { Accept: 'application/json' } });
        if (!keyRes.ok) {
            return;
        }
        const { publicKey } = await keyRes.json();
        if (!publicKey) {
            return;
        }
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: pushBase64ToBytes(publicKey).buffer,
        });
        await fetch('/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(sub.toJSON()),
        }).catch(() => {});
    } catch (error) {
        console.error('[push] gagal berlangganan:', error);
    }
}

async function pushManualEnable() {
    try {
        if (!('Notification' in window)) {
            alert('Browser ini tidak mendukung notifikasi.');
            return;
        }
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            alert('Izin notifikasi ditolak. Aktifkan lewat pengaturan browser/HP agar alarm tetap bunyi saat app ditutup.');
            return;
        }
        await pushEnsureSubscription();
        alert('Notifikasi darurat aktif di perangkat ini.');
    } catch (error) {
        console.error('[push] aktivasi manual gagal:', error);
    }
}

document.addEventListener('pointerdown', pushEnsureSubscription, { once: true });
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-push-enable]')) {
        pushManualEnable();
    }
});

if ('serviceWorker' in navigator && !window.__perumkuSwRegistered) {
    window.__perumkuSwRegistered = true;
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.error('[pwa] gagal mendaftarkan service worker:', error);
        });
    });
}
