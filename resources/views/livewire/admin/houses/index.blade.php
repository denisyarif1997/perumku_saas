<div class="space-y-4">
    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nomor / alamat..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="export" class="flex min-h-[48px] items-center gap-2 rounded-2xl border border-[#0F172A] bg-white px-4 font-semibold text-[#0F172A]">
                <i data-lucide="download" class="h-4 w-4"></i><span class="hidden sm:inline">Export</span>
            </button>
            <a href="{{ route('admin.houses.create') }}" wire:navigate
                class="flex min-h-[48px] min-w-[48px] items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white">
                <i data-lucide="plus" class="h-5 w-5"></i><span class="hidden sm:inline">Tambah</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2">
        <select wire:model.live="blockFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
            <option value="">Semua Blok</option>
            @foreach ($blocks as $block)
                <option value="{{ $block->id }}">Blok {{ $block->code }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
        </select>
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="space-y-2 md:hidden">
        @forelse ($houses as $house)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[16px] font-bold">Rumah {{ $house->fullLabel() }}</p>
                        <p class="mt-0.5 text-[13px] text-[#64748B]">{{ $house->address ?? '-' }}</p>
                    </div>
                    <x-ui.badge color="{{ $house->status === 'active' ? 'green' : 'slate' }}">{{ $house->status === 'active' ? '● Aktif' : '○ Nonaktif' }}</x-ui.badge>
                </div>
                <p class="mt-2 text-[14px] text-[#64748B]">Penghuni: <span class="font-medium text-[#0F172A]">{{ $house->houseResidents->first()?->resident?->name ?? '-' }}</span></p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('admin.houses.edit', $house) }}" wire:navigate class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold"><i data-lucide="pencil" class="h-4 w-4"></i> Ubah</a>
                    @if (($house->house_residents_count ?? 0) > 0 || ($house->outstanding_billings_count ?? 0) > 0)
                        <button disabled title="Tidak bisa dihapus karena masih ada data warga/tagihan"
                            class="flex min-h-[44px] cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-200 text-[14px] font-semibold text-slate-400"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                    @else
                        <button wire:click="delete({{ $house->id }})" wire:confirm="Hapus rumah {{ $house->fullLabel() }}?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="house" title="Belum ada rumah" subtitle="Tambahkan data rumah perumahan." />
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr><th class="px-4 py-3">Rumah</th><th class="px-4 py-3">Alamat</th><th class="px-4 py-3">Penghuni</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($houses as $house)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-semibold">{{ $house->fullLabel() }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $house->address ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $house->houseResidents->first()?->resident?->name ?? '-' }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $house->status === 'active' ? 'green' : 'slate' }}">{{ $house->status }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.houses.edit', $house) }}" wire:navigate class="font-semibold">Ubah</a>
                            @if (($house->house_residents_count ?? 0) > 0 || ($house->outstanding_billings_count ?? 0) > 0)
                                <button disabled title="Tidak bisa dihapus karena masih ada data warga/tagihan" class="ml-3 cursor-not-allowed font-semibold text-slate-400">Hapus</button>
                            @else
                                <button wire:click="delete({{ $house->id }})" wire:confirm="Hapus rumah ini?" class="ml-3 font-semibold text-red-600">Hapus</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-[#64748B]">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $houses->links() }}</div>
</div>
