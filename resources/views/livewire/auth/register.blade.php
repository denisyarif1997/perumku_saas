<div>
    {{-- Kartu form pendaftaran perumahan baru --}}
    <div class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] md:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[#134E4A]">Daftarkan: {{ config('app.name', 'Perumku') }}</h1>
                <p class="mt-1 text-[14px] text-[#64748B]">Isi formulir untuk mengajukan pendaftaran perumahan Anda.</p>
            </div>
            {{-- <span class="mt-1 shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">Gratis</span> --}}
        </div>

        <form wire:submit="register" class="mt-6 space-y-4">
            <p class="rounded-2xl bg-[#F8FAFC] p-4 text-[13px] leading-relaxed text-[#64748B]">Langkah 1 dari 2: buat akun dulu, lalu lengkapi data diri & perumahan Anda.</p>

            <x-ui.field label="Email" :error="$errors->first('email')">
                <input type="email" wire:model.live="email" placeholder="nama@email.com" autocomplete="email"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            <x-ui.field label="Password" :error="$errors->first('password')">
                <input type="password" wire:model.live="password" placeholder="Minimal 8 karakter" autocomplete="new-password"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            <x-ui.field label="Konfirmasi Password" :error="$errors->first('password_confirmation')">
                <input type="password" wire:model.live="password_confirmation" placeholder="Ulangi password" autocomplete="new-password"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            @error('email')
                @if (str_contains($message, 'terdaftar'))
                    <x-ui.alert type="warning">{{ $message }}</x-ui.alert>
                @endif
            @enderror

            <button type="submit" wire:loading.attr="disabled"
                class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 font-semibold text-white shadow-lg shadow-teal-700/30 transition hover:from-teal-500 hover:to-teal-600 disabled:opacity-70">
                <span wire:loading.remove>Lanjut Isi Data Perumahan</span>
                <span wire:loading>Memproses...</span>
            </button>
        </form>

        <div class="my-5 flex items-center gap-3 text-[12px] font-medium text-[#94A3B8]">
            <span class="h-px flex-1 bg-[#E2E8F0]"></span>
            ATAU
            <span class="h-px flex-1 bg-[#E2E8F0]"></span>
        </div>

        <a href="{{ route('auth.google.redirect') }}"
            class="flex min-h-[48px] w-full items-center justify-center gap-2.5 rounded-2xl border border-[#E2E8F0] bg-white font-semibold text-[#334155] transition hover:border-[#CBD5E1] hover:bg-[#F8FAFC]">
            <svg class="h-5 w-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                <path fill="#FBBC05" d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.98-3.09z"/>
                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/>
            </svg>
            Daftar dengan Google
        </a>

        <p class="mt-5 text-center text-[14px] text-[#64748B]">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-teal-700 hover:text-teal-600">Masuk di sini</a>
        </p>
    </div>
</div>