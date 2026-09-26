<div class="space-y-6">

    {{-- HERO SALAM --}}
    <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-teal-600 to-teal-800 p-6 text-white shadow-xl shadow-teal-900/20">
        {{-- Dekorasi playful ala clay 3D --}}
        <div class="pointer-events-none absolute -right-6 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-10 -left-8 h-28 w-28 rounded-full bg-amber-300/20"></div>
        <div class="pointer-events-none absolute right-10 top-10 h-10 w-10 rounded-2xl bg-amber-300/80 shadow-lg shadow-amber-900/20" style="transform: rotate(18deg);"></div>
        <div class="pointer-events-none absolute bottom-8 right-16 h-6 w-6 rounded-full bg-white/30"></div>

        <div class="relative">
            <p class="text-[13px] font-medium text-teal-100">Selamat datang 👋</p>
            <h1 class="mt-1 text-[24px] font-bold leading-tight">{{ explode(' ', $user->name)[0] }}</h1>
            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[13px] font-semibold">
                <i data-lucide="house" class="h-4 w-4"></i> {{ $house?->fullLabel() ?? 'Belum terverifikasi' }}
            </p>
        </div>
    </div>

    {{-- KARTU IPL --}}
    <div class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[15px] font-bold text-[#134E4A]">Iuran</p>
            <a href="{{ route('resident.ipl.index') }}" wire:navigate
                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-teal-50 px-3 py-1.5 text-[13px] font-semibold text-teal-700 transition active:scale-95">
                Lihat semua <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </a>
        </div>

        @if (($overdueBillings ?? collect())->isNotEmpty())
            <div class="mt-4 rounded-2xl bg-red-50 p-4">
                <p class="text-[13px] font-bold text-red-700">
                    {{ $overdueBillings->count() }} Iuran lewat jatuh tempo
                </p>
                <p class="mt-0.5 text-[13px] text-red-600">
                    Total tunggakan <span class="font-bold">@rupiah($overdueBillings->sum(fn ($b) => $b->remaining()))</span>
                </p>
                <div class="mt-3 space-y-2">
                    @foreach ($overdueBillings as $overdue)
                        <a href="{{ route('resident.ipl.show', $overdue) }}" wire:navigate
                            class="block rounded-2xl bg-white p-3.5 shadow-sm transition active:scale-[0.98]">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 flex-1 truncate text-[14px] font-bold text-slate-900">{{ $overdue->periodLabel() }}</p>
                                <x-ui.badge color="{{ $overdue->statusColor() }}" class="shrink-0">{{ $overdue->statusLabel() }}</x-ui.badge>
                            </div>
                            <p class="mt-0.5 truncate text-[12px] text-slate-500">
                                {{ $overdue->rateName() }} · JT {{ $overdue->due_date?->format('d/m/Y') ?? '-' }}
                            </p>
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <p class="text-[13px] font-bold text-red-700">@rupiah($overdue->remaining())</p>
                                <span class="inline-flex shrink-0 items-center gap-1 text-[12px] font-semibold text-teal-700">
                                    Detail <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-4 text-[13px] font-semibold text-[#64748B]">Iuran bulan ini ({{ \App\Support\Currency::period(now()->year, now()->month) }})</p>
        <div class="mt-2 space-y-2">
            @forelse (($currentBillings ?? collect()) as $billing)
                <a href="{{ route('resident.ipl.show', $billing) }}" wire:navigate
                    class="block rounded-2xl border border-[#EEF2F1] p-3.5 transition active:scale-[0.98] active:bg-slate-50">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14px] font-bold">{{ $billing->rateName() }}</p>
                            <p class="mt-0.5 text-[13px] text-[#64748B]">Rumah {{ $billing->house?->fullLabel() ?? '-' }} · JT {{ $billing->due_date?->format('d/m/Y') }}</p>
                        </div>
                        <x-ui.badge color="{{ $billing->statusColor() }}" class="shrink-0">{{ $billing->statusLabel() }}</x-ui.badge>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between gap-2">
                        <p class="text-[15px] font-bold">@rupiah($billing->remaining() > 0 ? $billing->remaining() : $billing->total)</p>
                        <span class="inline-flex shrink-0 items-center gap-1 text-[12px] font-semibold text-teal-700">
                            Detail <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                        </span>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl bg-[#F6F8F7] p-4 text-[13px] text-[#64748B]">
                    Belum ada Iuran untuk bulan ini.
                </div>
            @endforelse
        </div>

        @if ($outstandingCount > 0)
            <div class="mt-4 rounded-2xl bg-red-50 p-3.5 text-[13px] text-red-700">
                <span class="font-semibold">{{ $outstandingCount }} Iuran belum lunas</span> dengan total @rupiah($outstandingAmount)
            </div>
        @else
            <div class="mt-4 rounded-2xl bg-emerald-50 p-3.5 text-[13px] font-semibold text-emerald-700">
                Semua Iuran IPL sudah lunas. Terima kasih! 🎉
            </div>
        @endif
    </div>

    {{-- KARTU INFO DENGAN ILUSTRASI 3D CLAY --}}
    <a href="{{ route('resident.info.index') }}" wire:navigate
        class="group flex items-center gap-4 rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] transition active:scale-[0.98]">
        <div class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-[20px] bg-gradient-to-br from-amber-300 to-orange-400 shadow-lg shadow-orange-400/40">
            <div class="absolute inset-x-3 top-2 h-3 rounded-full bg-white/40"></div>
            <span class="relative text-[22px]">📢</span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[15px] font-bold text-[#134E4A]">Info & Pengumuman</p>
            <p class="truncate text-[13px] text-[#64748B]">Lihat pengumuman & kirim pengaduan</p>
        </div>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-50 text-teal-700 transition group-hover:bg-teal-100">
            <i data-lucide="chevron-right" class="h-4 w-4"></i>
        </span>
    </a>

    {{-- AKSES CEPAT --}}
    <div>
        <h2 class="mb-3 px-1 text-[16px] font-bold text-[#134E4A]">Akses Cepat</h2>
        <div class="grid grid-cols-3 gap-3 text-center text-[13px] font-medium">
            @foreach ([['wallet', 'Iuran', 'resident.ipl.index', 'from-teal-400 to-teal-600 shadow-teal-500/40'], ['messages-square', 'Forum', 'resident.forum.index', 'from-sky-400 to-blue-500 shadow-sky-500/40'], ['wrench', 'Aduan', 'resident.complaints.index', 'from-violet-400 to-purple-500 shadow-violet-500/40'], ['crown', 'Catur', 'resident.chess.index', 'from-amber-300 to-orange-400 shadow-amber-400/40'], ['megaphone', 'Info', 'resident.info.index', 'from-pink-400 to-rose-500 shadow-pink-500/40'], ['user', 'Profil', 'resident.profile', 'from-slate-400 to-slate-600 shadow-slate-500/40']] as [$icon, $label, $route, $gradient])
                @if ($route)
                    <a href="{{ route($route) }}" wire:navigate
                        class="flex min-h-[96px] flex-col items-center justify-center gap-2.5 rounded-[22px] bg-white p-3 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] transition active:scale-95">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[16px] bg-gradient-to-br {{ $gradient }} shadow-lg">
                            <i data-lucide="{{ $icon }}" class="h-5 w-5 text-white"></i>
                        </span>
                        {{ $label }}
                    </a>
                @else
                    <div class="flex min-h-[96px] flex-col items-center justify-center gap-2.5 rounded-[22px] bg-white p-3 opacity-50 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[16px] bg-gradient-to-br {{ $gradient }} shadow-lg">
                            <i data-lucide="{{ $icon }}" class="h-5 w-5 text-white"></i>
                        </span>
                        {{ $label }}
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
