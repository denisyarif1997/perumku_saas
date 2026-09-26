import { createIcons, icons } from 'lucide';

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
