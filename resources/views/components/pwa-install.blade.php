@props(['variant' => 'resident'])
{{-- Tombol "Install App": muncul hanya saat browser mengirim beforeinstallprompt.
     iPhone Safari: tampilkan panduan manual karena tidak ada prompt otomatis. --}}
<div x-data="pwaInstall()" x-show="visible" x-cloak
    class="rounded-2xl border border-teal-100 bg-white p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-lg shadow-teal-600/30">
            <i data-lucide="smartphone" class="h-5 w-5"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[14px] font-bold text-[#134E4A]">Install {{ config('app.name', 'Perumku') }}</p>
            <p class="truncate text-[12px] text-[#64748B]" x-text="subtitle"></p>
        </div>
        <button type="button" @click="install()"
            class="flex h-11 items-center gap-1.5 rounded-xl bg-teal-700 px-4 text-[13px] font-semibold text-white shadow-lg shadow-teal-700/30 transition active:scale-95">
            <i data-lucide="download" class="h-4 w-4"></i> Install
        </button>
    </div>
    <script>
        function pwaInstall() {
            return {
                visible: false,
                subtitle: 'Akses cepat dari layar utama HP kamu.',
                deferredPrompt: null,
                init() {
                    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
                    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                        || window.navigator.standalone === true;
                    if (isStandalone) return;
                    if (isIos) {
                        this.subtitle = 'Ketuk Bagikan lalu "Add to Home Screen".';
                        this.visible = true;
                        return;
                    }
                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this.deferredPrompt = e;
                        this.visible = true;
                    });
                    window.addEventListener('appinstalled', () => {
                        this.deferredPrompt = null;
                        this.visible = false;
                    });
                },
                async install() {
                    if (! this.deferredPrompt) return;
                    this.deferredPrompt.prompt();
                    await this.deferredPrompt.userChoice;
                    this.deferredPrompt = null;
                    this.visible = false;
                },
            };
        }
    </script>
</div>
