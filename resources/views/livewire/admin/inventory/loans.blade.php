<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div>
        <h1 class="text-[20px] font-bold">Pinjam Barang</h1>
        <p class="text-[14px] text-[#64748B]">Verifikasi pengajuan dan proses pelepasan barang</p>
    </div>

    {{-- Ringkasan antrean kerja --}}
    <div class="grid grid-cols-4 gap-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-amber-700">{{ $summary['requested'] }}</p>
            <p class="text-[12px] text-[#64748B]">Menunggu</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-sky-700">{{ $summary['approved'] }}</p>
            <p class="text-[12px] text-[#64748B]">Siap Diserah</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-purple-700">{{ $summary['loaned'] }}</p>
            <p class="text-[12px] text-[#64748B]">Dipinjamkan</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-emerald-700">{{ $summary['returned'] }}</p>
            <p class="text-[12px] text-[#64748B]">Selesai</p>
        </div>
    </div>

    {{-- Pencarian & Filter --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3">
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"></i>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari barang atau nama peminjam..."
                    class="min-h-[46px] w-full rounded-xl border border-[#E2E8F0] pl-9 pr-3 text-[15px] outline-none focus:border-teal-600">
            </div>
            <select wire:model.live="statusFilter" aria-label="Filter status"
                class="min-h-[46px] rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Daftar pengajuan --}}
    <div class="space-y-3">
        @forelse ($loans as $loan)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4" wire:key="loan-{{ $loan->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[16px] font-bold leading-snug">{{ $loan->item?->name ?? 'Barang dihapus' }}</p>
                        <p class="mt-0.5 text-[12px] text-[#64748B]">
                            {{ $loan->borrowerName() }}@if ($house = $loan->borrowerHouseLabel()) · {{ $house }}@endif
                        </p>
                    </div>
                    <x-ui.badge color="{{ $loan->statusColor() }}">{{ $loan->statusLabel() }}</x-ui.badge>
                </div>

                @if ($loan->purpose)
                    <p class="mt-2 text-[13px] text-[#64748B]">"{{ $loan->purpose }}"</p>
                @endif

                <p class="mt-2 font-mono text-[11px] text-[#94A3B8]">{{ $loan->referenceNumber() }}</p>

                {{-- Jejak waktu peminjaman --}}
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[12px] text-[#64748B]">
                    <span>Diajukan {{ $loan->created_at->format('d/m/Y H:i') }}</span>
                    @if ($loan->approved_at)
                        <span>Disetujui {{ $loan->approved_at->format('d/m/Y H:i') }}</span>
                    @endif
                    @if ($loan->loaned_at)
                        <span>Diberikan {{ $loan->loaned_at->format('d/m/Y H:i') }}</span>
                    @endif
                    @if ($loan->returned_at)
                        <span>Kembali {{ $loan->returned_at->format('d/m/Y H:i') }}</span>
                    @endif
                </div>

                @if ($loan->return_note)
                    <p class="mt-2 rounded-xl bg-slate-50 p-2.5 text-[13px] text-[#334155]">
                        <span class="font-semibold">Catatan pengembalian:</span> {{ $loan->return_note }}
                    </p>
                @endif

                {{-- Aksi yang tersedia mengikuti status pengajuan saat ini --}}
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[#E2E8F0] pt-3"
                    wire:target="approve,reject,handOver,openReturn">
                    @if ($loan->canTransitionTo('approved'))
                        <button type="button" wire:click="approve({{ $loan->id }})"
                            class="flex min-h-[38px] items-center gap-1.5 rounded-xl bg-teal-700 px-3.5 text-[13px] font-semibold text-white">
                            <i data-lucide="check" class="h-4 w-4"></i> Setujui
                        </button>
                        <button type="button" wire:click="reject({{ $loan->id }})"
                            wire:confirm="Tolak pengajuan pinjam ini?"
                            class="flex min-h-[38px] items-center gap-1.5 rounded-xl border border-red-200 px-3.5 text-[13px] font-semibold text-red-700">
                            <i data-lucide="x" class="h-4 w-4"></i> Tolak
                        </button>
                    @endif

                    @if ($loan->canTransitionTo('loaned'))
                        <button type="button" wire:click="handOver({{ $loan->id }})"
                            class="flex min-h-[38px] items-center gap-1.5 rounded-xl bg-purple-700 px-3.5 text-[13px] font-semibold text-white">
                            <i data-lucide="package-check" class="h-4 w-4"></i> Serahkan Barang
                        </button>
                    @endif

                    @if ($loan->canTransitionTo('returned'))
                        <button type="button" wire:click="openReturn({{ $loan->id }})"
                            class="flex min-h-[38px] items-center gap-1.5 rounded-xl border border-[#E2E8F0] px-3.5 text-[13px] font-semibold text-[#334155]">
                            <i data-lucide="undo-2" class="h-4 w-4"></i> Terima Kembali
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="repeat" title="Belum ada pengajuan" subtitle="Pengajuan pinjam dari warga akan tampil di sini." />
        @endforelse
    </div>

    <div>{{ $loans->links() }}</div>

    {{-- Formulir penerimaan barang kembali --}}
    @if ($returningId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" wire:click.self="closeReturn">
            <div class="flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-t-[24px] bg-white sm:rounded-[24px]">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-3">
                    <p class="font-bold text-[#134E4A]">Terima Barang Kembali</p>
                    <button type="button" wire:click="closeReturn" aria-label="Tutup form"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#E2E8F0]">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <form wire:submit="confirmReturn({{ $returningId }})"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <x-ui.field label="Catatan (opsional)">
                        <textarea wire:model="returnNote" rows="3" maxlength="500"
                            placeholder="Contoh: Barang kembali utuh."
                            class="w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] p-3.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                    </x-ui.field>

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="closeReturn"
                            class="min-h-[46px] rounded-2xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="confirmReturn"
                            class="min-h-[46px] rounded-2xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="confirmReturn">Terima</span>
                            <span wire:loading wire:target="confirmReturn">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

