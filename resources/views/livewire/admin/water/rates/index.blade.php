<div class="space-y-4">
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
        <input wire:model.live.debounce.300ms="search" placeholder="Cari nama tarif..."
            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Form tarif --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <p class="font-bold">{{ $editingId ? 'Ubah Tarif Air' : 'Tambah Tarif Air' }}</p>
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
                <input wire:model="name" placeholder="Contoh: Air 2026"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Tarif per m3 (Rp)" :error="$errors->first('price_per_m3')">
                <input wire:model="price_per_m3" type="number" min="0" step="100" inputmode="numeric" placeholder="5000"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Beban/Admin (Rp)" :error="$errors->first('admin_fee')">
                <input wire:model="admin_fee" type="number" min="0" step="500" inputmode="numeric"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Min Pakai (m3)" :error="$errors->first('min_usage_m3')">
                <input wire:model="min_usage_m3" type="number" min="0" step="1" inputmode="numeric"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Berlaku Mulai" :error="$errors->first('effective_date')">
                <input wire:model="effective_date" type="date"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Berlaku Sampai (opsional)" :error="$errors->first('end_date')">
                <input wire:model="end_date" type="date"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Status" :error="$errors->first('status')">
                <select wire:model="status" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </x-ui.field>

            <div class="md:col-span-2">
                <x-ui.field label="Deskripsi (opsional)" :error="$errors->first('description')">
                    <textarea wire:model="description" rows="2" maxlength="500"
                        class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-[#0F172A]"></textarea>
                </x-ui.field>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="flex min-h-[48px] w-full items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white">
                    {{ $editingId ? 'Simpan Perubahan' : 'Simpan Tarif Air' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Data tarif yang sudah ada --}}
    <div class="space-y-2 md:hidden">
        @forelse ($rates as $rate)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-bold">{{ $rate->name }}</p>
                        <p class="text-[13px] text-[#64748B]">{{ $rate->estate?->name ?? 'Semua Perumahan' }}</p>
                    </div>
                    <x-ui.badge color="{{ $rate->status === 'active' ? 'green' : 'slate' }}">{{ $rate->status === 'active' ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                </div>
                <p class="mt-2 text-[18px] font-bold">@rupiah($rate->price_per_m3) <span class="text-[13px] font-normal text-[#64748B]">/m³</span></p>
                <p class="text-[13px] text-[#64748B]">Beban @rupiah($rate->admin_fee) · Min {{ (float) $rate->min_usage_m3 }} m³</p>
                <p class="mt-1 text-[13px] text-[#64748B]">{{ $rate->effective_date?->format('d/m/Y') }} s/d {{ $rate->end_date?->format('d/m/Y') ?? 'sekarang' }}</p>
                @if ($rate->description)
                    <p class="mt-1 text-[13px] text-[#64748B]">{{ $rate->description }}</p>
                @endif
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button wire:click="edit({{ $rate->id }})" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold"><i data-lucide="pencil" class="h-4 w-4"></i> Ubah</button>
                    <button wire:click="delete({{ $rate->id }})" wire:confirm="Hapus tarif {{ $rate->name }}?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="tags" title="Belum ada tarif air" subtitle="Tambahkan tarif agar tagihan air bisa digenerate." />
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Nama Tarif</th>
                    <th class="px-4 py-3">Perumahan</th>
                    <th class="px-4 py-3">Tarif/m³</th>
                    <th class="px-4 py-3">Beban</th>
                    <th class="px-4 py-3">Min</th>
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
                        <td class="px-4 py-3 font-semibold">@rupiah($rate->price_per_m3)</td>
                        <td class="px-4 py-3">@rupiah($rate->admin_fee)</td>
                        <td class="px-4 py-3">{{ (float) $rate->min_usage_m3 }} m³</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $rate->effective_date?->format('d/m/Y') }} s/d {{ $rate->end_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $rate->status === 'active' ? 'green' : 'slate' }}">{{ $rate->status }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="edit({{ $rate->id }})" class="font-semibold">Ubah</button>
                            <button wire:click="delete({{ $rate->id }})" wire:confirm="Hapus tarif ini?" class="ml-3 font-semibold text-red-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-[#64748B]">Belum ada tarif air.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rates->links() }}</div>
</div>
