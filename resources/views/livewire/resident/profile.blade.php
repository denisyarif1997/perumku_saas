<div class="space-y-6">
    {{-- PROFIL HEADER DENGAN ILUSTRASI CLAY --}}
    <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-teal-600 to-teal-800 p-6 pt-8 text-white shadow-xl shadow-teal-900/20">
        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-8 -left-6 h-20 w-20 rounded-full bg-amber-300/20"></div>
        <div class="pointer-events-none absolute right-8 bottom-6 h-8 w-8 rounded-full bg-white/25"></div>
        <div class="relative flex items-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-amber-300 to-orange-400 text-xl font-bold text-teal-900 shadow-lg shadow-amber-900/30 ring-4 ring-white/30">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-[18px] font-bold">{{ $user->name }}</p>
                <p class="truncate text-[13px] text-teal-100">{{ $user->email }} • {{ $user->role?->name }}</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
        <x-ui.field label="Nama" :error="$errors->first('name')">
            <input wire:model.live="name" class="min-h-[48px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-4 focus:border-teal-600">
        </x-ui.field>
        <x-ui.field label="No. HP" :error="$errors->first('phone')">
            <input wire:model.live="phone" inputmode="tel" class="min-h-[48px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-4 focus:border-teal-600">
        </x-ui.field>
        <x-ui.field label="Password Baru (opsional)" :error="$errors->first('new_password')">
            <input type="password" wire:model.live="new_password" placeholder="Minimal 8 karakter" class="min-h-[48px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-4 focus:border-teal-600">
        </x-ui.field>
        <button class="flex min-h-[52px] w-full items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 font-semibold text-white shadow-lg shadow-teal-700/30 transition active:scale-[0.98]">
            <span wire:loading.remove>Simpan Perubahan</span><span wire:loading>Menyimpan...</span>
        </button>
    </form>

    <div class="space-y-2">
        <p class="px-1 text-[15px] font-bold text-[#134E4A]">Menu</p>
        @if (! $user->hasRole('resident'))
            <a href="{{ route('admin.dashboard') }}" class="flex min-h-[54px] items-center gap-3 rounded-2xl bg-white px-5 text-[15px] font-medium shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] transition active:scale-[0.98]"><i data-lucide="layout-dashboard" class="h-5 w-5 text-teal-700"></i>Dashboard Admin</a>
        @endif
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="flex min-h-[54px] w-full items-center gap-3 rounded-2xl bg-white px-5 text-[15px] font-semibold text-red-600 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] transition active:scale-[0.98]"><i data-lucide="log-out" class="h-5 w-5"></i>Keluar</button>
        </form>
    </div>
</div>
