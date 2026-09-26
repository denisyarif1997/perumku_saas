<div>
    {{-- Kartu form pendaftaran perumahan baru --}}
    <div class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] md:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[#134E4A]">Daftarkan: {{ config('app.name', 'Perumku') }}</h1>
                <p class="mt-1 text-[14px] text-[#64748B]">Isi formulir untuk mengajukan pendaftaran perumahan Anda.</p>
            </div>
            <span class="mt-1 shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">Gratis</span>
        </div>

        <form wire:submit="sendToWhatsapp" class="mt-6 space-y-4">
            <x-ui.field label="Kode Perumahan" :error="$errors->first('estateCode')">
                <input type="text" wire:model.live="estateCode" placeholder="mis. GARDEN-CITY"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] uppercase outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            <x-ui.field label="Nama Perumahan" :error="$errors->first('estateName')">
                <input type="text" wire:model.live="estateName" placeholder="mis. Garden City Residence"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            <x-ui.field label="Nama Lengkap Admin" :error="$errors->first('name')">
                <input type="text" wire:model.live="name" placeholder="Nama penanggung jawab"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            <x-ui.field label="Nomor HP / WhatsApp" :error="$errors->first('phone')">
                <input type="tel" wire:model.live="phone" placeholder="08xxxxxxxxxx"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

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

            <button type="submit" wire:loading.attr="disabled"
                class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 font-semibold text-white shadow-lg shadow-teal-700/30 transition hover:from-teal-500 hover:to-teal-600 disabled:opacity-70">
                <span wire:loading.remove>Kirim via WhatsApp</span>
                <span wire:loading>Memproses...</span>
            </button>
        </form>

        <p class="mt-5 text-center text-[14px] text-[#64748B]">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-teal-700 hover:text-teal-600">Masuk di sini</a>
        </p>
    </div>
</div>