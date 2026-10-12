<div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-xl shadow-slate-200/50">
    @if (! $invitation)
        <div class="text-center">
            <p class="text-lg font-bold text-[#134E4A]">Undangan tidak valid</p>
            <p class="mt-1 text-[14px] text-[#64748B]">Link kedaluwarsa / sudah dipakai. Minta admin kirim undangan baru.</p>
            <a href="{{ route('login') }}" wire:navigate class="mt-4 inline-flex min-h-[48px] items-center rounded-xl bg-teal-700 px-6 font-semibold text-white">Ke Halaman Masuk</a>
        </div>
    @else
        <p class="text-[13px] text-[#64748B]">Undangan {{ $invitation->estate?->name ?? 'Perumahan' }} • Rumah {{ $invitation->house?->fullLabel() ?? '-' }}</p>
        <h1 class="mt-1 text-xl font-bold text-[#134E4A]">Halo, {{ $invitation->name }}!</h1>
        <p class="mt-1 text-[14px] text-[#64748B]">Lengkapi data untuk mengaktifkan akun warga. Berlaku s/d {{ $invitation->expires_at?->format('d M Y') }}.</p>

        <form wire:submit="accept" class="mt-5 space-y-3">
            <x-ui.field label="Nama" :error="$errors->first('name')">
                <input wire:model="name" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
            </x-ui.field>
            <x-ui.field label="Email (untuk login)" :error="$errors->first('email')">
                <input wire:model="email" type="email" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
            </x-ui.field>
            <x-ui.field label="No. HP" :error="$errors->first('phone')">
                <input wire:model="phone" inputmode="tel" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
            </x-ui.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Password" :error="$errors->first('password')">
                    <input wire:model="password" type="password" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
                </x-ui.field>
                <x-ui.field label="Konfirmasi password">
                    <input wire:model="password_confirmation" type="password" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
                </x-ui.field>
            </div>
            @error('token') <p class="text-[13px] text-red-600">{{ $message }}</p> @enderror
            @error('email') <p class="text-[13px] text-red-600">{{ $message }}</p> @enderror
            <button class="flex min-h-[48px] w-full items-center justify-center rounded-xl bg-teal-700 font-semibold text-white" wire:loading.attr="disabled">
                <span wire:loading.remove>Aktifkan Akun</span><span wire:loading>Memproses...</span>
            </button>
        </form>
    @endif
</div>
