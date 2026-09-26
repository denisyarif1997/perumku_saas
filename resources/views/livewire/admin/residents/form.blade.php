<div>
    <a href="{{ route('admin.residents.index') }}" wire:navigate class="mb-4 inline-flex min-h-[44px] items-center gap-2 text-[14px] font-semibold">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali
    </a>
    <form wire:submit="save" class="space-y-4 rounded-2xl border border-[#E2E8F0] bg-white p-4 md:p-6">
        <x-ui.field label="Nama Lengkap" :error="$errors->first('name')">
            <input wire:model.live="name" placeholder="Nama warga" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
        </x-ui.field>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="NIK" :error="$errors->first('nik')">
                <input wire:model.live="nik" inputmode="numeric" placeholder="16 digit" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
            <x-ui.field label="No. HP" :error="$errors->first('phone')">
                <input wire:model.live="phone" inputmode="tel" placeholder="08xx" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Jenis Kelamin">
                <select wire:model.live="gender" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                    <option value="male">Laki-laki</option>
                    <option value="female">Perempuan</option>
                </select>
            </x-ui.field>
            <x-ui.field label="Tanggal Lahir">
                <input type="date" wire:model.live="birth_date" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
            </x-ui.field>
        </div>
        <x-ui.field label="Email (untuk akun login)" :error="$errors->first('email')">
            <input type="email" wire:model.live="email" placeholder="nama@email.com" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
        </x-ui.field>
        <x-ui.field label="Rumah" :error="$errors->first('house_id')">
            <select wire:model.live="house_id" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                <option value="">Pilih rumah</option>
                @foreach ($houses as $house)
                    <option value="{{ $house->id }}">Rumah {{ $house->fullLabel() }} — {{ $house->address }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <x-ui.field label="Hubungan dengan Rumah">
            <select wire:model.live="relationship" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] px-4">
                <option value="owner">Pemilik</option>
                <option value="spouse">Suami / Istri</option>
                <option value="child">Anak</option>
                <option value="parent">Orang Tua</option>
                <option value="tenant">Penyewa</option>
                <option value="other">Lainnya</option>
            </select>
        </x-ui.field>
        @if (! $resident)
            <label class="flex min-h-[44px] items-center gap-3 text-[14px]">
                <input type="checkbox" wire:model.live="create_account" class="h-5 w-5 accent-[#0F172A]">
                Buatkan akun login (password awal: password123)
            </label>
        @endif
        <button class="flex min-h-[48px] w-full items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white" wire:loading.attr="disabled">
            <span wire:loading.remove>Simpan</span><span wire:loading>Menyimpan...</span>
        </button>
    </form>
</div>
