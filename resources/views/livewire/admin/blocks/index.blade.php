<div class="space-y-4">
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
        <input wire:model.live.debounce.300ms="search" placeholder="Cari blok..."
            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 outline-none focus:border-[#0F172A]">
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <form wire:submit="save" class="space-y-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <h2 class="font-bold">Tambah Blok</h2>
        <x-ui.estate-field
            label="Perumahan"
            placeholder="Pilih perumahan"
            :estates="$estates"
            :error="$errors->first('housing_estate_id')"
            show-code
            live
            input-class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4"
        />
        <div class="grid grid-cols-2 gap-3">
            <x-ui.field label="Kode" :error="$errors->first('code')">
                <input wire:model.live="code" placeholder="A" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
            <x-ui.field label="Nama" :error="$errors->first('name')">
                <input wire:model.live="name" placeholder="Blok A" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
        </div>
        <button class="flex min-h-[48px] w-full items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white sm:w-auto sm:px-8">
            <span wire:loading.remove>Simpan</span><span wire:loading>Menyimpan...</span>
        </button>
    </form>

    <div class="space-y-2">
        @forelse ($blocks as $block)
            <div class="flex items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-bold">{{ $block->code }}</div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $block->name }}</p>
                    <p class="text-[13px] text-[#64748B]">{{ $block->estate?->name }} • {{ $block->houses_count }} rumah</p>
                </div>
                @if (($block->houses_count ?? 0) > 0)
                    <button disabled title="Tidak bisa dihapus karena masih ada {{ $block->houses_count }} rumah"
                        class="flex h-11 w-11 shrink-0 cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 text-slate-400"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                @else
                    <button wire:click="delete({{ $block->id }})" wire:confirm="Hapus blok {{ $block->code }}?" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-red-200 text-red-600"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                @endif
            </div>
        @empty
            <x-ui.empty-state title="Belum ada blok" />
        @endforelse
    </div>
    <div>{{ $blocks->links() }}</div>
</div>
