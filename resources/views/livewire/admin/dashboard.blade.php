@php
    $hasStats = $canManageHouses || $canManageResidents || $canManageUsers;
    $hasFinance = $canManageBilling || $canViewPayments;
@endphp

<div class="space-y-6">
    @if ($hasStats)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($canManageHouses)
                <div class="rounded-[22px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                    <div class="flex items-center justify-between">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[14px] bg-gradient-to-br from-teal-400 to-teal-600 shadow-lg shadow-teal-500/40"><i data-lucide="house" class="h-5 w-5 text-white"></i></span>
                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-teal-700">Rumah</span>
                    </div>
                    <p class="mt-5 text-3xl font-bold text-[#134E4A]">{{ $totalHouses }}</p>
                    <p class="mt-1 text-sm text-[#64748B]">Total Rumah</p>
                    <div class="mt-3 flex items-center justify-between text-[12px] text-[#64748B]">
                        <span>Aktif: <span class="font-semibold">{{ $activeHouses }}</span></span>
                        <span>Nonaktif: <span class="font-semibold">{{ $inactiveHouses }}</span></span>
                    </div>
                </div>
            @endif

            @if ($canManageResidents)
                <div class="rounded-[22px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                    <div class="flex items-center justify-between">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[14px] bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-lg shadow-emerald-500/40"><i data-lucide="users" class="h-5 w-5 text-white"></i></span>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">Warga</span>
                    </div>
                    <p class="mt-5 text-3xl font-bold text-[#134E4A]">{{ $totalResidents }}</p>
                    <p class="mt-1 text-sm text-[#64748B]">Total terdaftar</p>
                    <div class="mt-3 text-[12px] text-[#64748B]">Aktif: <span class="font-semibold">{{ $activeResidents }}</span></div>
                </div>
            @endif

            @if ($canManageHouses)
                <div class="rounded-[22px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                    <div class="flex items-center justify-between">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[14px] bg-gradient-to-br from-violet-400 to-purple-500 shadow-lg shadow-violet-500/40"><i data-lucide="layout-grid" class="h-5 w-5 text-white"></i></span>
                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-violet-700">Blok</span>
                    </div>
                    <p class="mt-5 text-3xl font-bold text-[#134E4A]">{{ $totalBlocks }}</p>
                    <p class="mt-1 text-sm text-[#64748B]">Total area</p>
                    <div class="mt-3 text-[12px] text-[#64748B]">Tersedia untuk pengelolaan</div>
                </div>
            @endif

            @if ($canManageUsers)
                <div class="rounded-[22px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                    <div class="flex items-center justify-between">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[14px] bg-gradient-to-br from-amber-300 to-orange-400 shadow-lg shadow-amber-400/40"><i data-lucide="user-cog" class="h-5 w-5 text-white"></i></span>
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-amber-700">User</span>
                    </div>
                    <p class="mt-5 text-3xl font-bold text-[#134E4A]">{{ $totalUsers }}</p>
                    <p class="mt-1 text-sm text-[#64748B]">Total User</p>
                    <div class="mt-3 text-[12px] text-[#64748B]">Role & akses terkelola</div>
                </div>
            @endif
        </section>
    @endif

    @unless ($hasStats || $hasFinance)
        <x-ui.empty-state icon="shield-off" title="Belum ada modul yang bisa diakses"
            subtitle="Hubungi administrator untuk memberikan hak akses pada role Anda." />
    @endunless

    @if ($hasFinance)
        <section class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#94A3B8]">Periode berjalan</p>
                    <h2 class="text-xl font-bold text-[#134E4A]">IPL {{ $periodLabel }}</h2>
                </div>
                @if ($canManageBilling)
                    <a href="{{ route('admin.ipl.billings.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-100">
                        Kelola Tagihan <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                @endif
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl bg-[#F6F8F7] p-4">
                    <p class="text-[12px] font-medium text-[#64748B]">Total tagihan</p>
                    <p class="mt-2 text-2xl font-bold text-[#1E293B]">@rupiah($periodTotal)</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4">
                    <p class="text-[12px] font-medium text-emerald-700">Terkumpul</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-800">@rupiah($periodPaid)</p>
                </div>
                <div class="rounded-2xl bg-red-50 p-4">
                    <p class="text-[12px] font-medium text-red-700">Tunggakan</p>
                    <p class="mt-2 text-2xl font-bold text-red-800">@rupiah($periodOutstanding)</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4">
                    <p class="text-[12px] font-medium text-amber-700">Belum lunas</p>
                    <p class="mt-2 text-2xl font-bold text-amber-800">{{ $periodUnpaidCount }}</p>
                </div>
                <div class="rounded-2xl bg-sky-50 p-4">
                    <p class="text-[12px] font-medium text-sky-700">Rasio pembayaran</p>
                    <p class="mt-2 text-2xl font-bold text-sky-800">{{ $collectionRate }}%</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <a href="{{ route('admin.ipl.payments.index') }}" wire:navigate class="flex items-center justify-between rounded-2xl bg-[#F6F8F7] px-4 py-3 text-sm text-[#1E293B] transition hover:bg-teal-50">
                    <span class="flex items-center gap-2"><i data-lucide="clock" class="h-4 w-4 text-teal-700"></i> Menunggu verifikasi</span>
                    <span class="flex items-center gap-2 font-semibold">
                        <span>@rupiah($pendingPaymentsAmount)</span>
                        <span class="rounded-full {{ $pendingPayments > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600' }} px-2 py-0.5 text-[11px] font-bold">{{ $pendingPayments }}</span>
                    </span>
                </a>

                <div class="flex items-center justify-between rounded-2xl bg-[#F6F8F7] px-4 py-3 text-sm text-[#1E293B]">
                    <span class="flex items-center gap-2"><i data-lucide="wallet" class="h-4 w-4"></i> Penagihan bulan ini</span>
                    <span class="font-semibold text-slate-900">@rupiah($thisMonthPayments)</span>
                </div>
            </div>
        </section>

        @if ($overdueBillingsCount > 0)
            <section class="rounded-3xl border border-rose-200 bg-rose-50 p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-rose-500">Peringatan</p>
                        <h3 class="text-lg font-bold text-rose-900">Tagihan terlambat</h3>
                    </div>
                    <span class="rounded-full bg-white px-2 py-1 text-xs font-bold text-rose-700">{{ $overdueBillingsCount }} item</span>
                </div>
                <div class="space-y-2">
                    @foreach ($overdueBillings->take(3) as $billing)
                        <div class="flex items-center justify-between rounded-2xl border border-rose-200 bg-white px-3 py-2 text-sm">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $billing->house?->fullLabel() ?? '-' }}</p>
                                <p class="text-slate-500">{{ $billing->resident?->name ?? '-' }} · {{ $billing->periodLabel() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-slate-900">@rupiah($billing->total)</p>
                                <p class="text-xs text-rose-600">Jatuh tempo {{ $billing->due_date?->format('d/m/Y') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Pembayaran Terbaru</h2>
                    <a href="{{ route('admin.ipl.payments.index') }}" wire:navigate class="text-sm font-semibold text-slate-700">Lihat Semua</a>
                </div>
                <div class="space-y-2">
                    @forelse ($recentPayments as $payment)
                        <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700"><i data-lucide="receipt" class="h-5 w-5"></i></div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $payment->resident?->name ?? $payment->billing?->house?->fullLabel() ?? '-' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $payment->payment_date?->format('d/m/Y') }} · {{ $payment->methodLabel() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-900">@rupiah($payment->amount)</p>
                                <x-ui.badge color="{{ $payment->statusColor() }}">{{ $payment->statusLabel() }}</x-ui.badge>
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state icon="receipt" title="Belum ada pembayaran" subtitle="Generate tagihan IPL terlebih dahulu." />
                    @endforelse
                </div>
            </div>

            @if ($canManageResidents)
            <div class="mt-4 rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-[#134E4A]">Warga Terbaru</h2>
                    <a href="{{ route('admin.residents.index') }}" wire:navigate class="text-sm font-semibold text-teal-700">Lihat Semua</a>
                </div>
                <div class="space-y-2">
                    @forelse ($recentResidents as $resident)
                        <div class="flex items-center gap-3 rounded-2xl bg-[#F6F8F7] p-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-teal-400 to-teal-600 text-sm font-bold text-white shadow-md shadow-teal-500/40">{{ strtoupper(substr($resident->name, 0, 1)) }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-[#1E293B]">{{ $resident->name }}</p>
                                <p class="truncate text-xs text-[#64748B]">{{ $resident->phone ?? '-' }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">{{ $resident->status ?? 'active' }}</span>
                        </div>
                    @empty
                        <x-ui.empty-state icon="users" title="Belum ada warga" subtitle="Tambahkan data warga terlebih dahulu." />
                    @endforelse
                </div>
            </div>
            @endif
        </section>
    @endif

    @if ($canManageHouses)
        <section class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-[#134E4A]">Rumah Terbaru</h2>
                <a href="{{ route('admin.houses.index') }}" wire:navigate class="text-sm font-semibold text-teal-700">Lihat Semua</a>
            </div>
            <div class="space-y-2">
                @forelse ($recentHouses as $house)
                    <div class="flex items-center gap-3 rounded-2xl bg-[#F6F8F7] p-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[14px] bg-gradient-to-br from-sky-400 to-blue-500 text-sm font-bold text-white shadow-md shadow-sky-500/40">{{ $house->block?->code }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-[#1E293B]">Rumah {{ $house->fullLabel() }}</p>
                            <p class="truncate text-xs text-[#64748B]">{{ $house->houseResidents->first()?->resident?->name ?? 'Belum ada penghuni' }}</p>
                        </div>
                        <x-ui.badge color="green">{{ $house->occupancy_status }}</x-ui.badge>
                    </div>
                @empty
                    <x-ui.empty-state title="Belum ada rumah" subtitle="Tambahkan data rumah terlebih dahulu." />
                @endforelse
            </div>
        </section>
    @endif
</div>
