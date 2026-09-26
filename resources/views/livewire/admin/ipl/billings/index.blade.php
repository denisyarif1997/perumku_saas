@php use App\Support\Currency; @endphp

<div class="space-y-6">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 lg:p-5">
            <p class="text-[12px] text-[#64748B]">Jumlah Tagihan</p>
            <p class="mt-1 text-xl font-bold lg:text-2xl">{{ number_format($summary['count'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 lg:p-5">
            <p class="text-[12px] text-[#64748B]">Total Tagihan</p>
            <p class="mt-1 text-xl font-bold lg:text-2xl">@rupiah($summary['total'])</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 lg:p-5">
            <p class="text-[12px] text-[#64748B]">Sudah Dibayar</p>
            <p class="mt-1 text-xl font-bold text-emerald-700 lg:text-2xl">@rupiah($summary['paid'])</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 lg:p-5">
            <p class="text-[12px] text-[#64748B]">Belum Lunas</p>
            <p class="mt-1 text-xl font-bold text-red-600 lg:text-2xl">{{ number_format($summary['unpaid'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="space-y-3">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari invoice / nama / nomor rumah..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <select wire:model.live="statusFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">Semua Status</option>
                <option value="unpaid">Belum Bayar</option>
                <option value="partial">Bayar Sebagian</option>
                <option value="paid">Lunas</option>
                <option value="cancelled">Dibatalkan</option>
            </select>
            <select wire:model.live="typeFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">IPL + Air</option>
                <option value="ipl">IPL</option>
                <option value="water">Air</option>
            </select>
            <select wire:model.live="blockFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">Semua Blok</option>
                @foreach ($blocks as $block)
                    <option value="{{ $block->id }}">Blok {{ $block->code }}</option>
                @endforeach
            </select>
            <select wire:model.live="periodMonth" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">Semua Bulan</option>
                @foreach ($months as $number => $label)
                    <option value="{{ $number }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="periodYear" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">Semua Tahun</option>
                @foreach (range(now()->year + 1, now()->year - 3) as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center justify-between gap-2">
            <button wire:click="resetFilter" class="text-[14px] font-semibold text-[#64748B] underline">Reset filter</button>
            <button type="button" wire:click="export" class="flex min-h-[48px] items-center gap-2 rounded-2xl border border-[#0F172A] bg-white px-4 font-semibold text-[#0F172A]">
                <i data-lucide="download" class="h-4 w-4"></i><span>Export</span>
            </button>
        </div>
    </div>

    {{-- Mobile list --}}
    <div class="space-y-2 md:hidden">
        @forelse ($billings as $billing)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[16px] font-bold">Rumah {{ $billing->house?->fullLabel() ?? '-' }}</p>
                        <p class="mt-0.5 truncate font-mono text-[12px] text-[#64748B]">{{ $billing->invoice_number }}</p>
                        <p class="truncate text-[13px] text-[#64748B]">{{ $billing->resident?->name ?? 'Tanpa penghuni' }} · {{ $billing->periodLabel() }}</p>
                    </div>
                    <x-ui.badge color="{{ $billing->statusColor() }}" class="shrink-0">{{ $billing->statusLabel() }}</x-ui.badge>
                </div>
                <p class="mt-2 inline-flex max-w-full items-center gap-1 truncate rounded-full bg-slate-100 px-2 py-0.5 text-[12px] font-semibold text-slate-700">
                    <i data-lucide="tag" class="h-3.5 w-3.5 shrink-0 text-slate-500"></i>
                    <span class="truncate">[{{ $billing->typeLabel() }}] {{ $billing->isWater() ? ($billing->waterRate?->name ?? 'Tarif air tidak tercatat') : ($billing->iplRate?->name ?? 'Tarif tidak tercatat') }}</span>
                </p>
                @if ($billing->isWater())
                    <p class="mt-1 text-[12px] text-[#64748B]">Meter {{ $billing->meter_start }} → {{ $billing->meter_end }} m³ · Pakai {{ $billing->usage_m3 }} m³</p>
                @endif
                <div class="mt-2 flex items-end justify-between gap-2">
                    <div>
                        <p class="text-[18px] font-bold">@rupiah($billing->total)</p>
                        <p class="text-[13px] text-[#64748B]">Dibayar @rupiah($billing->paid_amount) · Sisa @rupiah($billing->remaining())</p>
                    </div>
                    <p class="text-[12px] text-[#64748B]">JT {{ $billing->due_date?->format('d/m/Y') }}</p>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('admin.ipl.billings.show', $billing) }}" wire:navigate class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold"><i data-lucide="eye" class="h-4 w-4"></i> Detail</a>
                    @if ($billing->status !== 'paid' && $billing->status !== 'cancelled')
                        <button wire:click="startMarkPaid({{ $billing->id }})" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-emerald-200 text-[14px] font-semibold text-emerald-700"><i data-lucide="check-circle-2" class="h-4 w-4"></i> Lunas</button>
                    @else
                        <button wire:click="delete({{ $billing->id }})" wire:confirm="Hapus tagihan {{ $billing->invoice_number }}?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="file-text" title="Belum ada tagihan" subtitle="Generate tagihan IPL terlebih dahulu." />
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-x-auto rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full min-w-[1080px] text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="whitespace-nowrap px-5 py-4">Invoice</th>
                    <th class="whitespace-nowrap px-5 py-4">Rumah</th>
                    <th class="whitespace-nowrap px-5 py-4">Penghuni</th>
                    <th class="whitespace-nowrap px-5 py-4">Periode</th>
                    <th class="whitespace-nowrap px-5 py-4">Tarif</th>
                    <th class="whitespace-nowrap px-5 py-4">Tagihan</th>
                    <th class="whitespace-nowrap px-5 py-4">Dibayar</th>
                    <th class="whitespace-nowrap px-5 py-4">Status</th>
                    <th class="whitespace-nowrap px-5 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($billings as $billing)
                    <tr class="border-t border-[#E2E8F0] transition hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-4 font-mono text-[13px]">{{ $billing->invoice_number }}</td>
                        <td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $billing->house?->fullLabel() ?? '-' }}</td>
                        <td class="max-w-[200px] truncate px-5 py-4 text-[#64748B]">{{ $billing->resident?->name ?? '-' }}</td>
                        <td class="whitespace-nowrap px-5 py-4">{{ $billing->periodLabel() }}</td>
                        <td class="px-5 py-4">
                            <span class="inline-flex max-w-[220px] items-center gap-1.5 truncate rounded-full bg-slate-100 px-3 py-1 text-[12px] font-semibold text-slate-700" title="{{ $billing->isWater() ? ($billing->waterRate?->name ?? 'Tarif air tidak tercatat') : ($billing->iplRate?->name ?? 'Tarif tidak tercatat') }}">
                                <i data-lucide="tag" class="h-3.5 w-3.5 shrink-0 text-slate-500"></i>
                                <span class="truncate">[{{ $billing->typeLabel() }}] {{ $billing->isWater() ? ($billing->waterRate?->name ?? 'Tarif air tidak tercatat') : ($billing->iplRate?->name ?? 'Tarif tidak tercatat') }}</span>
                            </span>
                            @if ($billing->isWater())
                                <p class="mt-1 text-[12px] text-[#64748B]">{{ $billing->meter_start }}→{{ $billing->meter_end }} m³ ({{ $billing->usage_m3 }} m³)</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 font-semibold">@rupiah($billing->total)</td>
                        <td class="whitespace-nowrap px-5 py-4">@rupiah($billing->paid_amount)</td>
                        <td class="whitespace-nowrap px-5 py-4"><x-ui.badge color="{{ $billing->statusColor() }}">{{ $billing->statusLabel() }}</x-ui.badge></td>
                        <td class="whitespace-nowrap px-5 py-4 text-right">
                            <a href="{{ route('admin.ipl.billings.show', $billing) }}" wire:navigate class="font-semibold">Detail</a>
                            @if ($billing->status !== 'paid' && $billing->status !== 'cancelled')
                                <button wire:click="startMarkPaid({{ $billing->id }})" class="ml-4 font-semibold text-emerald-700">Lunas</button>
                            @endif
                            <button wire:click="delete({{ $billing->id }})" wire:confirm="Hapus tagihan ini?" class="ml-4 font-semibold text-red-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-[#64748B]">Belum ada tagihan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $billings->links() }}</div>

    {{-- Popup tandai lunas: pilih kas tujuan pemasukan --}}
    @if ($markPaidId)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
            <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold">Tandai Tagihan Lunas</p>
                        <p class="text-[13px] text-[#64748B]">Pembayaran tunai akan langsung terverifikasi dan dicatat sebagai kas masuk.</p>
                    </div>
                    <button wire:click="cancelMarkPaid" class="flex h-9 w-9 items-center justify-center rounded-xl border"><i data-lucide="x" class="h-4 w-4"></i></button>
                </div>

                <form wire:submit="confirmMarkPaid" class="mt-3 space-y-3">
                    <x-ui.field label="Masuk ke Kas" :error="$errors->first('markPaid_cash_account_id')">
                        <select wire:model="markPaid_cash_account_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            <option value="">— Tanpa pencatatan kas —</option>
                            @foreach ($cashAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} (Saldo @rupiah($account->currentBalance()))</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    @if ($cashAccounts->isEmpty())
                        <p class="rounded-xl bg-amber-50 p-3 text-[13px] text-amber-700">Belum ada kas terdaftar. Tagihan tetap bisa ditandai lunas tanpa pencatatan kas.</p>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="cancelMarkPaid" class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white font-semibold">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white font-semibold">Tandai Lunas</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
</div>