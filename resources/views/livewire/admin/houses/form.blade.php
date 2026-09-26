<div>
    <a href="{{ route('admin.houses.index') }}" wire:navigate class="mb-4 inline-flex min-h-[44px] items-center gap-2 text-[14px] font-semibold">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali
    </a>

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-[#E2E8F0] bg-white p-4 md:p-6">
        <x-ui.field label="Blok" :error="$errors->first('housing_block_id')">
            <select wire:model.live="housing_block_id" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                <option value="">Pilih blok</option>
                @foreach ($blocks as $block)
                    <option value="{{ $block->id }}">Blok {{ $block->code }} — {{ $block->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Nomor Rumah" :error="$errors->first('house_number')">
            <input wire:model.live="house_number" placeholder="Contoh: 12" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
        </x-ui.field>

        <x-ui.field label="Alamat" :error="$errors->first('address')">
            <textarea wire:model.live="address" rows="2" placeholder="Jl. Contoh Blok A No. 12" class="w-full rounded-2xl border border-[#E2E8F0] px-4 py-3"></textarea>
        </x-ui.field>

        <div class="grid grid-cols-2 gap-3">
            <x-ui.field label="Luas Tanah (m²)">
                <input type="number" step="0.01" wire:model.live="land_area" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
            <x-ui.field label="Luas Bangunan (m²)">
                <input type="number" step="0.01" wire:model.live="building_area" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
        </div>

        <x-ui.field label="Status Kepemilikan">
            <select wire:model.live="ownership_status" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                <option value="owner">Milik Sendiri</option>
                <option value="rent">Sewa / Kontrak</option>
                <option value="developer">Developer</option>
                <option value="other">Lainnya</option>
            </select>
        </x-ui.field>

        <x-ui.field label="Status Hunian">
            <select wire:model.live="occupancy_status" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                <option value="occupied">Dihuni</option>
                <option value="empty">Kosong</option>
                <option value="renovation">Renovasi</option>
            </select>
        </x-ui.field>

        <button class="flex min-h-[48px] w-full items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white" wire:loading.attr="disabled">
            <span wire:loading.remove>Simpan</span><span wire:loading>Menyimpan...</span>
        </button>
    </form>
</div>
