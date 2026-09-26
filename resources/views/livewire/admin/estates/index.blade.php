@php
    use App\Models\HousingEstate;
@endphp

<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif
    @if (session('info'))
        <x-ui.alert type="info" icon="info">{{ session('info') }}</x-ui.alert>
    @endif

    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari perumahan..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
        <button type="button" wire:click="create"
            class="flex min-h-[48px] items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white">
            <i data-lucide="plus" class="h-5 w-5"></i><span class="hidden sm:inline">Tambah</span>
        </button>
    </div>

    <form wire:submit="save" class="space-y-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <h2 class="font-bold">Tambah Perumahan</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-ui.field label="Kode" :error="$errors->first('code')">
                <input wire:model.live="code" placeholder="HH-01" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
            <x-ui.field label="Nama" :error="$errors->first('name')">
                <input wire:model.live="name" placeholder="Nama perumahan" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
        </div>
        <button type="submit" class="flex min-h-[48px] w-full items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white sm:w-auto sm:px-8">
            <span wire:loading.remove>Simpan</span><span wire:loading>Menyimpan...</span>
        </button>
    </form>

    @if ($editingId)
        <form wire:submit="update" class="space-y-3 rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sky-800">Ubah Perumahan</h2>
                <button type="button" wire:click="cancelEdit" class="text-[14px] font-semibold text-slate-600 hover:underline">Batal</button>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.field label="Kode" :error="$errors->first('editCode')">
                    <input wire:model.live="editCode" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                </x-ui.field>
                <x-ui.field label="Nama" :error="$errors->first('editName')">
                    <input wire:model.live="editName" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                </x-ui.field>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.field label="Alamat" :error="$errors->first('editAddress')">
                    <textarea wire:model.live="editAddress" rows="2" class="w-full min-h-[48px] rounded-2xl border border-[#E2E8F0] px-4 py-2.5"></textarea>
                </x-ui.field>
                <x-ui.field label="Telepon" :error="$errors->first('editPhone')">
                    <input wire:model.live="editPhone" type="tel" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                </x-ui.field>
            </div>
            <x-ui.field label="Email" :error="$errors->first('editEmail')">
                <input wire:model.live="editEmail" type="email" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
            <button type="submit" class="flex min-h-[48px] w-full items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white sm:w-auto sm:px-8">
                <span wire:loading.remove>Simpan Perubahan</span><span wire:loading>Menyimpan...</span>
            </button>
        </form>
    @endif

    <div class="space-y-2 md:hidden">
        @forelse ($estates as $estate)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-[12px] text-[#64748B]">{{ $estate->code }}</p>
                        <p class="mt-0.5 text-[16px] font-bold">{{ $estate->name }}</p>
                        <p class="mt-1 text-[13px] text-[#64748B]">{{ $estate->blocks_count }} blok · {{ $estate->houses_count }} rumah</p>
                    </div>
                    <x-ui.badge color="{{ $estate->status === 'active' ? 'green' : 'slate' }}">{{ $estate->status }}</x-ui.badge>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="edit({{ $estate->id }})" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold">
                        <i data-lucide="pencil" class="h-4 w-4"></i> Ubah
                    </button>
                    @if ($estate->houses_count > 0 || $estate->blocks_count > 0)
                        <button type="button" disabled title="Tidak bisa dihapus karena masih ada data rumah/blok"
                            class="flex min-h-[44px] cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-200 text-[14px] font-semibold text-slate-400">
                            <i data-lucide="trash-2" class="h-4 w-4"></i> Hapus
                        </button>
                    @else
                        <button type="button" wire:click="delete({{ $estate->id }})" wire:confirm="Hapus {{ $estate->name }}?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700">
                            <i data-lucide="trash-2" class="h-4 w-4"></i> Hapus
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="building-2" title="Belum ada perumahan" subtitle="Tambahkan perumahan pertama di atas." />
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Alamat</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Blok</th>
                    <th class="px-4 py-3">Rumah</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($estates as $estate)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-mono text-[13px]">{{ $estate->code }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $estate->name }}</td>
                        <td class="px-4 py-3 max-w-[160px] truncate text-[#64748B]">{{ $estate->address ?? '-' }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $estate->phone ?? $estate->email ?? '-' }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $estate->status === 'active' ? 'green' : 'slate' }}">{{ ucfirst($estate->status) }}</x-ui.badge></td>
                        <td class="px-4 py-3">{{ $estate->blocks_count }}</td>
                        <td class="px-4 py-3">{{ $estate->houses_count }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <button type="button" wire:click="edit({{ $estate->id }})" class="font-semibold">Ubah</button>
                            @if ($estate->houses_count > 0 || $estate->blocks_count > 0)
                                <button type="button" disabled title="Tidak bisa dihapus karena masih ada data rumah/blok" class="ml-2 cursor-not-allowed font-semibold text-slate-400">Hapus</button>
                            @else
                                <button type="button" wire:click="delete({{ $estate->id }})" wire:confirm="Hapus {{ $estate->name }}?" class="ml-2 font-semibold text-red-600">Hapus</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-[#64748B]">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $estates->links() }}</div>
</div>
