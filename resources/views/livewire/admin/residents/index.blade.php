<div class="space-y-4">
    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nama / NIK / HP..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 outline-none focus:border-[#0F172A]">
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="export" class="flex min-h-[48px] items-center gap-2 rounded-2xl border border-[#0F172A] bg-white px-4 font-semibold text-[#0F172A]">
                <i data-lucide="download" class="h-4 w-4"></i><span class="hidden sm:inline">Export</span>
            </button>
            <a href="{{ route('admin.residents.create') }}" wire:navigate class="flex min-h-[48px] items-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white">
                <i data-lucide="plus" class="h-5 w-5"></i><span class="hidden sm:inline">Tambah</span>
            </a>
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="space-y-2 md:hidden">
        @forelse ($residents as $resident)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold">{{ strtoupper(substr($resident->name, 0, 1)) }}</div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $resident->name }}</p>
                        <p class="truncate text-[13px] text-[#64748B]">{{ $resident->houseResidents->first()?->house?->fullLabel() ? 'Rumah ' . $resident->houseResidents->first()->house->fullLabel() : $resident->phone ?? '-' }}</p>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('admin.residents.edit', $resident) }}" wire:navigate class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border text-[14px] font-semibold"><i data-lucide="pencil" class="h-4 w-4"></i> Ubah</a>
                    <button wire:click="delete({{ $resident->id }})" wire:confirm="Hapus warga ini?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="users" title="Belum ada warga" subtitle="Tambahkan data warga perumahan." />
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-2xl border bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[#64748B]"><tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">NIK</th><th class="px-4 py-3">Rumah</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($residents as $resident)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-semibold">{{ $resident->name }}</td>
                        <td class="px-4 py-3">{{ $resident->nik ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $resident->houseResidents->first()?->house?->fullLabel() ?? '-' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.residents.edit', $resident) }}" wire:navigate class="font-semibold">Ubah</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-[#64748B]">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $residents->links() }}</div>
</div>
