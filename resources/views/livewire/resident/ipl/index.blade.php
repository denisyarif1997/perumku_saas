<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Ringkasan Iuran aktif --}}
    <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-teal-600 to-teal-800 p-5 text-white shadow-xl shadow-teal-900/20">
        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-8 right-12 h-14 w-14 rounded-full bg-amber-300/20"></div>
        <p class="text-[13px] font-medium text-teal-100">Total Iuran Belum Lunas</p>
        <p class="mt-1 text-[30px] font-bold">@rupiah($summary['outstandingAmount'])</p>
        <p class="text-[13px] text-teal-100">{{ $summary['outstandingCount'] }} Iuran menunggu pembayaran</p>
        <div class="mt-3 flex items-center justify-between rounded-2xl bg-white/10 p-3.5 text-[13px]">
            <span>Sudah dibayar tahun {{ now()->year }}</span>
            <span class="font-bold">@rupiah($summary['paidThisYear'])</span>
        </div>
    </div>

    {{-- Filter --}}
    <div class="grid grid-cols-3 gap-2">
        <select wire:model.live="typeFilter" class="min-h-[48px] w-full rounded-2xl border-0 bg-white px-3 text-[14px] shadow-[0_4px_16px_-4px_rgba(19,78,74,0.10)] focus:ring-2 focus:ring-teal-500">
            <option value="">IPL + Air</option>
            <option value="ipl">IPL</option>
            <option value="water">Air</option>
        </select>
        <select wire:model.live="statusFilter" class="min-h-[48px] w-full rounded-2xl border-0 bg-white px-3 text-[14px] shadow-[0_4px_16px_-4px_rgba(19,78,74,0.10)] focus:ring-2 focus:ring-teal-500">
            <option value="">Semua Status</option>
            <option value="unpaid">Belum Bayar</option>
            <option value="partial">Bayar Sebagian</option>
            <option value="paid">Lunas</option>
            <option value="cancelled">Dibatalkan</option>
        </select>
        <select wire:model.live="yearFilter" class="min-h-[48px] w-full rounded-2xl border-0 bg-white px-3 text-[14px] shadow-[0_4px_16px_-4px_rgba(19,78,74,0.10)] focus:ring-2 focus:ring-teal-500">
            <option value="">Semua Tahun</option>
            @foreach ($years as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>
    </div>

    {{-- Daftar Iuran --}}
    <div class="space-y-3">
        @forelse ($billings as $billing)
            <a href="{{ route('resident.ipl.show', $billing) }}" wire:navigate
                class="block rounded-[22px] bg-white p-4 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] transition active:scale-[0.98]">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[16px] font-bold">{{ $billing->periodLabel() }}</p>
                        <p class="mt-0.5 truncate font-mono text-[12px] text-[#64748B]">{{ $billing->invoice_number }}</p>
                        <p class="truncate text-[13px] text-[#64748B]">Rumah {{ $billing->house?->fullLabel() ?? '-' }}</p>
                    </div>
                    <x-ui.badge color="{{ $billing->statusColor() }}" class="shrink-0">{{ $billing->statusLabel() }}</x-ui.badge>
                </div>
                <p class="mt-2 inline-flex max-w-full items-center gap-1 truncate rounded-full bg-slate-100 px-2 py-0.5 text-[12px] font-semibold text-slate-700">
                    <i data-lucide="tag" class="h-3.5 w-3.5 shrink-0 text-slate-500"></i>
                    <span class="truncate">[{{ $billing->typeLabel() }}] {{ $billing->rateName() }}</span>
                </p>
                @if ($billing->isWater())
                    <p class="mt-1 text-[12px] text-[#64748B]">Meter {{ $billing->meter_start }}→{{ $billing->meter_end }} m³ · Pakai {{ $billing->usage_m3 }} m³</p>
                @endif
                <div class="mt-2 flex items-end justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[18px] font-bold">@rupiah($billing->total)</p>
                        @if ($billing->remaining() > 0 && $billing->paid_amount > 0)
                            <p class="text-[13px] text-[#64748B]">Sisa @rupiah($billing->remaining())</p>
                        @endif
                        <p class="mt-0.5 text-[12px] text-[#64748B]">
                            JT {{ $billing->due_date?->format('d/m/Y') ?? '-' }}
                            @if ($billing->isOverdue())
                                · <span class="font-semibold text-red-600">{{ $billing->due_date->startOfDay()->diffInDays(now()->startOfDay()) }} hari terlambat</span>
                            @endif
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[13px] font-semibold text-[#64748B]">
                        Detail <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </span>
                </div>
            </a>
        @empty
            <x-ui.empty-state icon="file-text" title="Belum ada Iuran IPL"
                subtitle="Iuran akan muncul setelah pengelola melakukan generate periode." />
        @endforelse
    </div>

    <div>{{ $billings->links() }}</div>
</div>