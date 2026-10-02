<div class="space-y-4">
    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nama tarif..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif
    @if (session('info'))
        <x-ui.alert type="info" icon="info">{{ session('info') }}</x-ui.alert>
    @endif

    {{-- Form tarif --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <p class="font-bold">{{ $editingId ? 'Ubah Tarif Iuran' : 'Tambah Tarif Iuran' }}</p>
            @if ($editingId)
                <button wire:click="cancel" class="text-[14px] font-semibold text-[#64748B]">Batal</button>
            @endif
        </div>

        <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
            <x-ui.estate-field
                :estates="$estates"
                :error="$errors->first('housing_estate_id')"
            />

            <x-ui.field label="Nama Tarif" :error="$errors->first('name')">
                <input wire:model="name" placeholder="Contoh: Iuran Bulanan 2026"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Nominal (Rp)" :error="$errors->first('amount')">
                <input wire:model="amount" type="number" min="0" step="1000" inputmode="numeric" placeholder="150000"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Periode" :error="$errors->first('period_type')">
                <select wire:model="period_type" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="monthly">Per Bulan</option>
                    <option value="quarterly">Per 3 Bulan</option>
                    <option value="yearly">Per Tahun</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Berlaku Mulai" :error="$errors->first('effective_date')">
                <input wire:model="effective_date" type="date"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Berakhir (opsional)" :error="$errors->first('end_date')">
                <input wire:model="end_date" type="date"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Status" :error="$errors->first('status')">
                <select wire:model="status" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Keterangan (opsional)" :error="$errors->first('description')">
                <input wire:model="description" placeholder="Catatan tambahan"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <div class="md:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white disabled:opacity-60">
                    <i data-lucide="{{ $editingId ? 'save' : 'plus' }}" class="h-5 w-5"></i>
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Tambah Tarif' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Daftar tarif --}}
    <div class="space-y-2 md:hidden">
        @forelse ($rates as $rate)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[16px] font-bold">{{ $rate->name }}</p>
                        <p class="mt-0.5 text-[13px] text-[#64748B]">{{ $rate->estate?->name ?? 'Semua Perumahan' }} · {{ $rate->periodLabel() }}</p>
                    </div>
                    <x-ui.badge color="{{ $rate->status === 'active' ? 'green' : 'slate' }}">{{ $rate->status === 'active' ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                </div>
                <p class="mt-2 text-[18px] font-bold">@rupiah($rate->amount)</p>
                <p class="text-[13px] text-[#64748B]">
                    {{ $rate->effective_date?->format('d/m/Y') }} s/d {{ $rate->end_date?->format('d/m/Y') ?? 'sekarang' }}
                </p>
                @if ($rate->description)
                    <p class="mt-1 text-[13px] text-[#64748B]">{{ $rate->description }}</p>
                @endif
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button wire:click="edit({{ $rate->id }})" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold"><i data-lucide="pencil" class="h-4 w-4"></i> Ubah</button>
                    <button wire:click="delete({{ $rate->id }})" wire:confirm="Hapus tarif {{ $rate->name }}?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="tags" title="Belum ada tarif iuran" subtitle="Tambahkan tarif agar tagihan bisa digenerate." />
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Nama Tarif</th>
                    <th class="px-4 py-3">Perumahan</th>
                    <th class="px-4 py-3">Nominal</th>
                    <th class="px-4 py-3">Periode</th>
                    <th class="px-4 py-3">Berlaku</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rates as $rate)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-semibold">{{ $rate->name }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $rate->estate?->name ?? 'Semua Perumahan' }}</td>
                        <td class="px-4 py-3 font-semibold">@rupiah($rate->amount)</td>
                        <td class="px-4 py-3">{{ $rate->periodLabel() }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $rate->effective_date?->format('d/m/Y') }} s/d {{ $rate->end_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $rate->status === 'active' ? 'green' : 'slate' }}">{{ $rate->status }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="edit({{ $rate->id }})" class="font-semibold">Ubah</button>
                            <button wire:click="delete({{ $rate->id }})" wire:confirm="Hapus tarif ini?" class="ml-3 font-semibold text-red-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-[#64748B]">Belum ada tarif iuran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rates->links() }}</div>
</div>