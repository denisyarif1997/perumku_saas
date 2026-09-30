<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div>
        <h1 class="text-[22px] font-bold">Pinjam Barang</h1>
        <p class="text-[14px] text-[#64748B]">Pilih barang yang dibutuhkan untuk kegiatan warga</p>
    </div>

    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-amber-700">{{ $summary['requested'] }}</p>
            <p class="text-[12px] text-[#64748B]">Menunggu</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-purple-700">{{ $summary['loaned'] }}</p>
            <p class="text-[12px] text-[#64748B]">Dipinjam</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-emerald-700">{{ $summary['returned'] }}</p>
            <p class="text-[12px] text-[#64748B]">Selesai</p>
        </div>
    </div>

    {{-- Pencarian --}}
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"></i>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari barang..."
            class="min-h-[46px] w-full rounded-xl border border-[#E2E8F0] bg-white pl-9 pr-3 text-[15px] outline-none focus:border-teal-600">
    </div>

    {{-- Daftar barang yang bisa dipinjam --}}
    <div class="space-y-2">
        @forelse ($items as $item)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4" wire:key="item-{{ $item->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[16px] font-bold leading-snug">{{ $item->name }}</p>
                        <p class="mt-0.5 text-[12px] text-[#64748B]">{{ $item->location ?: 'Lokasi akan disampaikan saat pengambilan' }}</p>
                    </div>
                    <x-ui.badge color="slate">{{ $item->availableQuantity() }} {{ $item->unit }}</x-ui.badge>
                </div>

                @if ($item->description)
                    <p class="mt-2 text-[13px] text-[#64748B]">{{ $item->description }}</p>
                @endif

                <button type="button" wire:click="requestLoan({{ $item->id }})"
                    class="mt-3 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 text-[14px] font-semibold text-white">
                    <i data-lucide="hand-platter" class="h-4 w-4"></i> Pinjam
                </button>
            </div>
        @empty
            <x-ui.empty-state icon="package" title="Belum ada barang" subtitle="Barang yang bisa dipinjam akan tampil di sini." />
        @endforelse
    </div>
    <div>{{ $items->links() }}</div>

    {{-- Riwayat peminjaman milik warga ini --}}
    @if ($loans->isNotEmpty())
        <div>
            <h2 class="mb-2 text-[15px] font-bold">Riwayat Pinjaman Saya</h2>
            <div class="space-y-2">
                @foreach ($loans as $loan)
                    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3.5" wire:key="loan-{{ $loan->id }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 flex-1 truncate text-[14px] font-bold">{{ $loan->item?->name ?? 'Barang dihapus' }}</p>
                            <x-ui.badge color="{{ $loan->statusColor() }}">{{ $loan->statusLabel() }}</x-ui.badge>
                        </div>
                        <p class="mt-1 text-[12px] text-[#64748B]">Diajukan {{ $loan->created_at->format('d/m/Y') }}</p>
                        @if ($loan->returned_at)
                            <p class="mt-0.5 text-[12px] text-[#64748B]">Dikembalikan {{ $loan->returned_at->format('d/m/Y') }}</p>
                        @endif

                        @if ($loan->canTransitionTo('cancelled'))
                            <button type="button" wire:click="cancel({{ $loan->id }})"
                                wire:confirm="Batalkan pengajuan pinjam ini?"
                                class="mt-2 text-[13px] font-semibold text-red-600 underline">
                                Batalkan
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Form pengajuan --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center" wire:click.self="closeForm">
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white">
                <div class="flex shrink-0 items-center justify-between p-5 pb-3">
                    <p class="text-lg font-bold">Ajukan Pinjam</p>
                    <button type="button" wire:click="closeForm" aria-label="Tutup form"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#E2E8F0]">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form wire:submit="submit" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <x-ui.field label="Alasan Peminjaman" :error="$errors->first('purpose')">
                        <textarea wire:model="purpose" rows="3" maxlength="255"
                            placeholder="Contoh: Untuk acara 17 Agustus di halaman."
                            class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                    </x-ui.field>

                    <p class="rounded-xl bg-slate-50 p-3 text-[12px] text-[#64748B]">
                        Pengajuan menunggu persetujuan pengelola. Setelah disetujui, barang bisa diambil
                        dari lokasi yang ditentukan pengelola.
                    </p>

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="closeForm"
                            class="flex min-h-[48px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                            class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Kirim</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

