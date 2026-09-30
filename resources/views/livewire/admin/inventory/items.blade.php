<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[20px] font-bold">Inventaris Barang</h1>
            <p class="text-[14px] text-[#64748B]">Kelola barang yang bisa dipinjam warga</p>
        </div>
        <button type="button" wire:click="createItem"
            class="flex min-h-[44px] shrink-0 items-center gap-2 rounded-xl bg-teal-700 px-4 text-[14px] font-semibold text-white transition hover:bg-teal-800">
            <i data-lucide="plus" class="h-4 w-4"></i> Tambah Barang
        </button>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-slate-800">{{ $summary['total'] }}</p>
            <p class="text-[12px] text-[#64748B]">Jenis Barang</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-sky-700">{{ $summary['units'] }}</p>
            <p class="text-[12px] text-[#64748B]">Total Unit</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-purple-700">{{ $summary['borrowed'] }}</p>
            <p class="text-[12px] text-[#64748B]">Sedang Dipinjam</p>
        </div>
    </div>

    {{-- Pencarian & Filter --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3">
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"></i>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau kode barang..."
                    class="min-h-[46px] w-full rounded-xl border border-[#E2E8F0] pl-9 pr-3 text-[15px] outline-none focus:border-teal-600">
            </div>
            <select wire:model.live="statusFilter" aria-label="Filter status"
                class="min-h-[46px] rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">Semua status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Daftar barang --}}
    <div class="space-y-3">
        @forelse ($items as $item)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[16px] font-bold leading-snug">{{ $item->name }}</p>
                        <p class="mt-0.5 text-[12px] text-[#64748B]">
                            @if ($item->code)
                                <span class="font-mono">{{ $item->code }}</span> ·
                            @endif
                            {{ $item->location ?: 'Lokasi belum dicatat' }}
                        </p>
                    </div>
                    <x-ui.badge color="{{ $item->statusColor() }}">{{ $item->statusLabel() }}</x-ui.badge>
                </div>

                @if ($item->description)
                    <p class="mt-2 line-clamp-2 text-[13px] text-[#64748B]">{{ $item->description }}</p>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[#E2E8F0] pt-3">
                    <x-ui.badge color="slate">
                        {{ $item->availableQuantity() }} / {{ $item->quantity }} {{ $item->unit }} tersedia
                    </x-ui.badge>
                    @if ($item->borrowed_count > 0)
                        <x-ui.badge color="purple">{{ $item->borrowed_count }} sedang dipinjam</x-ui.badge>
                    @endif
                    <div class="ml-auto flex items-center gap-1">
                        <button type="button" wire:click="edit({{ $item->id }})"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-[#E2E8F0] text-[#334155]"
                            title="Ubah barang">
                            <i data-lucide="pencil" class="h-4 w-4"></i>
                        </button>
                        <button type="button" wire:click="delete({{ $item->id }})"
                            wire:confirm="Hapus barang &quot;{{ $item->name }}&quot; beserta riwayat pinjamannya?"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 text-red-700"
                            title="Hapus barang">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="package" title="Belum ada barang" subtitle="Tambahkan barang yang bisa dipinjam warga.">
                <button type="button" wire:click="createItem"
                    class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 font-semibold text-white">
                    <i data-lucide="plus" class="h-4 w-4"></i> Tambah Barang Pertama
                </button>
            </x-ui.empty-state>
        @endforelse
    </div>

    <div>{{ $items->links() }}</div>

    {{-- Form tambah / ubah barang --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" wire:click.self="closeForm">
            <div class="flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-[24px] bg-white sm:rounded-[24px]">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-3">
                    <p class="font-bold text-[#134E4A]">{{ $editingId ? 'Ubah Barang' : 'Tambah Barang' }}</p>
                    <button type="button" wire:click="closeForm" aria-label="Tutup form"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#E2E8F0]">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <form wire:submit="save" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <x-ui.field label="Nama Barang" :error="$errors->first('name')">
                        <input wire:model="name" maxlength="150" placeholder="Contoh: Kursi Lipat"
                            class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px] outline-none focus:border-teal-600">
                    </x-ui.field>

                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.field label="Kode" :error="$errors->first('code')">
                            <input wire:model="code" maxlength="30" placeholder="INV-001"
                                class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px] outline-none focus:border-teal-600">
                        </x-ui.field>
                        <x-ui.field label="Lokasi" :error="$errors->first('location')">
                            <input wire:model="location" maxlength="150" placeholder="Gudang A"
                                class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px] outline-none focus:border-teal-600">
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.field label="Jumlah" :error="$errors->first('quantity')">
                            <input wire:model="quantity" type="number" inputmode="numeric" min="0" max="10000"
                                class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px] outline-none focus:border-teal-600">
                        </x-ui.field>
                        <x-ui.field label="Satuan" :error="$errors->first('unit')">
                            <input wire:model="unit" maxlength="30" placeholder="buah, set, lembar"
                                class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px] outline-none focus:border-teal-600">
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Status" :error="$errors->first('status')">
                        <select wire:model="status"
                            class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px]">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Deskripsi" :error="$errors->first('description')">
                        <textarea wire:model="description" rows="3" maxlength="1000"
                            placeholder="Keterangan singkat tentang barang (opsional)."
                            class="w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] p-3.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                    </x-ui.field>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button type="button" wire:click="closeForm"
                            class="min-h-[46px] rounded-2xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                            class="min-h-[46px] rounded-2xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
