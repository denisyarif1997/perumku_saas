<div>
    <div class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)] md:p-6">
        <div>
            <h1 class="text-2xl font-bold text-[#134E4A]">Lengkapi Data Diri & Perumahan</h1>
            <p class="mt-1 text-[14px] text-[#64748B]">Satu langkah lagi: isi data diri dan perumahan Anda.</p>
        </div>

        @if (session('status'))
            <x-ui.alert type="success" class="mt-4">{{ session('status') }}</x-ui.alert>
        @endif

        <form wire:submit="save" class="mt-6 space-y-4">
            <h2 class="text-[15px] font-bold text-[#134E4A]">Data Diri Calon Admin</h2>

            <x-ui.field label="Nama Lengkap" :error="$errors->first('name')">
                <input type="text" wire:model.live="name" placeholder="Nama penanggung jawab"
                    class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
            </x-ui.field>

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.field label="Email" :error="$errors->first('email')">
                    <input type="email" wire:model.live="email" placeholder="nama@email.com" autocomplete="email"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>

                <x-ui.field label="Nomor HP / WhatsApp" :error="$errors->first('phone')">
                    <input type="tel" wire:model.live="phone" placeholder="08xxxxxxxxxx"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>
            </div>

            @if ($method === 'email' && !$registrationId && !auth()->user()?->password)
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Password" :error="$errors->first('password')">
                        <input type="password" wire:model.live="password" placeholder="Minimal 8 karakter" autocomplete="new-password"
                            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                    </x-ui.field>

                    <x-ui.field label="Konfirmasi Password" :error="$errors->first('password_confirmation')">
                        <input type="password" wire:model.live="password_confirmation" placeholder="Ulangi password" autocomplete="new-password"
                            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                    </x-ui.field>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.field label="NIK (opsional)" :error="$errors->first('nik')">
                    <input type="text" wire:model.live="nik" placeholder="16 digit" inputmode="numeric"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>

                <x-ui.field label="Jenis Kelamin (opsional)" :error="$errors->first('gender')">
                    <select wire:model.live="gender"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                        <option value="">— Pilih —</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </select>
                </x-ui.field>

                <x-ui.field label="Tanggal Lahir (opsional)" :error="$errors->first('birth_date')">
                    <input type="date" wire:model.live="birth_date"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>
            </div>

            <h2 class="pt-2 text-[15px] font-bold text-[#134E4A]">Data Perumahan</h2>


            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.field label="Kode Perumahan" :error="$errors->first('estateCode')">
                    <input type="text" wire:model.live="estateCode" placeholder="mis. GARDEN-CITY"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] uppercase outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>

                <x-ui.field label="Nama Perumahan" :error="$errors->first('estateName')">
                    <input type="text" wire:model.live="estateName" placeholder="mis. Garden City Residence"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>
            </div>

            <x-ui.field label="Alamat Perumahan (opsional)" :error="$errors->first('estateAddress')">
                <textarea wire:model.live="estateAddress" rows="2" placeholder="Jalan, kota, provinsi"
                    class="w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 py-3 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10"></textarea>
            </x-ui.field>

            {{-- <div class="grid gap-4 md:grid-cols-2">
                <x-ui.field label="Telepon Perumahan (opsional)" :error="$errors->first('estatePhone')">
                    <input type="tel" wire:model.live="estatePhone" placeholder="08xxxxxxxxxx"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>

                <x-ui.field label="Email Perumahan (opsional)" :error="$errors->first('estateEmail')">
                    <input type="email" wire:model.live="estateEmail" placeholder="info@perumahan.id"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>
            </div> --}}

            <h2 class="pt-2 text-[15px] font-bold text-[#134E4A]">Rumah Pertama <span class="font-normal text-[#64748B]">(opsional)</span></h2>
            <p class="text-[13px] text-[#64748B]">Isi bila Anda juga tinggal di perumahan ini — akun Anda otomatis terikat sebagai warga pemilik rumah ini.</p>

            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.field label="Kode Blok" :error="$errors->first('blockCode')">
                    <input type="text" wire:model.live="blockCode" placeholder="mis. A"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] uppercase outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>

                <x-ui.field label="Nama Blok (opsional)" :error="$errors->first('blockName')">
                    <input type="text" wire:model.live="blockName" placeholder="mis. Blok Anggrek"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>

                <x-ui.field label="Nomor Rumah" :error="$errors->first('houseNumber')">
                    <input type="text" wire:model.live="houseNumber" placeholder="mis. 12"
                        class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-4 text-[16px] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10">
                </x-ui.field>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 font-semibold text-white shadow-lg shadow-teal-700/30 transition hover:from-teal-500 hover:to-teal-600 disabled:opacity-70">
                <span wire:loading.remove>{{ $registrationId ? 'Perbarui Pengajuan' : 'Kirim Pengajuan' }}</span>
                <span wire:loading>Menyimpan...</span>
            </button>
        </form>

        <p class="mt-5 text-center text-[14px] text-[#64748B]">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-teal-700 hover:text-teal-600">Masuk di sini</a>
        </p>
    </div>
</div>
