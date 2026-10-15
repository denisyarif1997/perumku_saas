/**
 * PANIC ALERT WATCHER (bagian 1: state + unlock audio).
 * Bagian 2: sirene + getar. Bagian 3: banner + notifikasi + polling.
 */
const PANIC_SEEN_KEY = 'perumku_seen_panic_ids';

function panicSeenIds() {
    try {
        const parsed = JSON.parse(localStorage.getItem(PANIC_SEEN_KEY) || '[]');
        return new Set(Array.isArray(parsed) ? parsed : []);
    } catch {
        return new Set();
    }
}

function panicSaveSeen(seen) {
    try {
        localStorage.setItem(PANIC_SEEN_KEY, JSON.stringify([...seen].slice(-60)));
    } catch {
        // abaikan: mode privat dsb.
    }
}

let panicAudioCtx = null;

function panicEnsureAudio() {
    try {
        if (!panicAudioCtx) {
            const AudioCtor = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtor) {
                return;
            }
            panicAudioCtx = new AudioCtor();
        }
        if (panicAudioCtx.state === 'suspended') {
            panicAudioCtx.resume().catch(() => {});
        }
    } catch (error) {
        console.error('[panic] audio tidak tersedia:', error);
    }
}

// Browser hanya mengizinkan suara setelah ada interaksi pengguna.
document.addEventListener('pointerdown', panicEnsureAudio);
document.addEventListener('keydown', panicEnsureAudio);

// Minta izin system-notification sekali, tepat setelah interaksi pertama.
document.addEventListener('pointerdown', () => {
    try {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
    } catch {
        // abaikan
    }
}, { once: true });

function panicPlaySiren(durationMs = 6000) {
    try {
        panicEnsureAudio();
        if (!panicAudioCtx) {
            return;
        }
        const ctx = panicAudioCtx;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        gain.gain.value = 0.12;
        osc.type = 'sine';
        osc.frequency.value = 660;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        let high = false;
        const step = window.setInterval(() => {
            high = !high;
            osc.frequency.setValueAtTime(high ? 880 : 660, ctx.currentTime);
        }, 450);
        window.setTimeout(() => {
            window.clearInterval(step);
            try {
                gain.gain.linearRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
                osc.stop(ctx.currentTime + 0.35);
            } catch {
                // abaikan
            }
        }, durationMs);
    } catch (error) {
        console.error('[panic] gagal membunyikan sirene:', error);
    }
}

function panicVibrate() {
    try {
        if ('vibrate' in navigator) {
            navigator.vibrate([500, 200, 500, 200, 800]);
        }
    } catch {
        // abaikan
    }
}


function panicShowBanner(alert) {
    try {
        let banner = document.getElementById('perumku-panic-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'perumku-panic-banner';
            banner.className = 'fixed inset-x-0 top-2 z-[99999] flex justify-center px-3';
            banner.innerHTML = [
                '<div class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border-2 border-red-600 bg-red-600 p-3 text-white shadow-2xl">',
                '<span class="flex h-10 w-10 shrink-0 animate-pulse items-center justify-center rounded-xl bg-white text-2xl">!</span>',
                '<div class="min-w-0 flex-1">',
                '<p class="text-sm font-bold">ALARM DARURAT</p>',
                '<p class="panic-text mt-0.5 truncate text-[13px]"></p>',
                '<a class="panic-link mt-1.5 inline-block rounded-lg bg-white px-3 py-1 text-xs font-bold text-red-700" href="#">Lihat Alarm</a>',
                '</div>',
                '<button type="button" class="panic-close rounded-lg px-2 py-1 text-lg leading-none" aria-label="Tutup">X</button>',
                '</div>',
            ].join('');
            document.body.appendChild(banner);
            banner.querySelector('.panic-close').addEventListener('click', () => banner.remove());
        }
        const who = alert.actor ? alert.actor + ': ' : '';
        banner.querySelector('.panic-text').textContent =
            who + (alert.excerpt || 'Ada warga membutuhkan bantuan segera.');
        banner.querySelector('.panic-link').setAttribute('href', alert.url || '/resident/panic');
        banner.classList.remove('hidden');
    } catch (error) {
        console.error('[panic] gagal menampilkan banner:', error);
    }
}

function panicSystemNotify(alert) {
    try {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }
        const who = alert.actor ? alert.actor + ': ' : '';
        const note = new Notification(alert.title || 'Alarm Darurat', {
            body: who + (alert.excerpt || 'Ada warga membutuhkan bantuan segera.'),
            tag: 'panic-' + alert.id,
            renotify: true,
        });
        note.onclick = () => {
            try {
                window.focus();
            } catch {
                // abaikan
            }
            if (alert.url) {
                window.location.href = alert.url;
            }
        };
    } catch (error) {
        console.error('[panic] system notification gagal:', error);
    }
}

function panicCheckWatcher() {
    const nodes = document.querySelectorAll('[data-panic-watcher]');
    if (nodes.length === 0) {
        return;
    }
    const seen = panicSeenIds();
    let changed = false;
    nodes.forEach((node) => {
        let alerts = [];
        try {
            alerts = JSON.parse(node.getAttribute('data-alerts') || '[]');
        } catch {
            alerts = [];
        }
        (Array.isArray(alerts) ? alerts : []).forEach((alert) => {
            if (!alert || !alert.id || seen.has(alert.id)) {
                return;
            }
            seen.add(alert.id);
            changed = true;
            panicPlaySiren();
            panicVibrate();
            panicShowBanner(alert);
            panicSystemNotify(alert);
        });
    });
    if (changed) {
        panicSaveSeen(seen);
    }
}

document.addEventListener('DOMContentLoaded', panicCheckWatcher);
document.addEventListener('livewire:navigated', panicCheckWatcher);
document.addEventListener('livewire:init', () => {
    try {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => panicCheckWatcher());
        });
    } catch {
        // abaikan: Livewire belum siap
    }
});