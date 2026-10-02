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

    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Generate Tagihan Iuran</p>
        <p class="mt-1 text-[14px] text-[#64748B]">
            Pilih tarif lalu centang warga/rumah yang akan ditagih. Yang tidak dicentang tidak dibuatkan tagihan.
            Tarif baru bisa ditagihkan ke periode yang sama tanpa menghapus tagihan lama.
        </p>

        <form wire:submit="generate" class="mt-4 grid gap-3 md:grid-cols-2">
            <x-ui.field label="Bulan Periode" :error="$errors->first('period_month')">
                <select wire:model.live="period_month" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    @foreach (\App\Support\Currency::MONTHS as $number => $label)
                        <option value="{{ $number }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Tahun Periode" :error="$errors->first('period_year')">
                <select wire:model.live="period_year" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    @foreach (range(now()->year - 2, now()->year + 1) as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.estate-field
                :estates="$estates"
                :error="$errors->first('housing_estate_id')"
                live
                hint=""
            />

            <x-ui.field label="Tarif Iuran" :error="$errors->first('ipl_rate_id')">
                <select wire:model.live="ipl_rate_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="">Otomatis (tarif berlaku)</option>
                    @foreach ($rates as $option)
                        <option value="{{ $option->id }}">{{ $option->name }} — @rupiah($option->amount) ({{ $option->estate?->name ?? 'Semua' }})</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Tanggal Jatuh Tempo" :error="$errors->first('due_day')">
                <input wire:model.live="due_day" type="number" min="1" max="28" inputmode="numeric"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <div class="md:col-span-2">
                <div class="rounded-2xl border border-[#E2E8F0] p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-bold">Pilih Warga yang Ditagih ({{ count($selectedHouses) }} dipilih)</p>
                            <p class="mt-1 text-[13px] text-[#64748B]">Yang tidak dicentang tidak akan dibuatkan tagihan.</p>
                        </div>
                        <button type="button" wire:click="toggleSelectAll" class="rounded-xl border border-[#E2E8F0] px-3 py-2 text-[14px] font-semibold">
                            {{ $selectAll ? 'Hapus Semua' : 'Pilih Semua' }}
                        </button>
                    </div>

                    <div class="relative mt-3">
                        <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
                        <input wire:model.live.debounce.300ms="houseSearch" placeholder="Cari rumah / warga..."
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
                    </div>

                    @error('selectedHouses')<p class="mt-2 text-[13px] font-semibold text-red-600">{{ $message }}</p>@enderror

                    <div class="mt-3 max-h-[320px] space-y-2 overflow-y-auto pr-1">
                        @forelse ($houses as $house)
                            <label class="flex items-center gap-3 rounded-xl border border-[#E2E8F0] px-3 py-2.5 {{ $house['billed'] ? 'bg-slate-50' : 'bg-white' }}">
                                <input type="checkbox" wire:model.live="selectedHouses" value="{{ $house['id'] }}" class="h-5 w-5 accent-[#0F172A]">
                                <span class="flex-1">
                                    <span class="block text-[14px] font-bold">Rumah {{ $house['label'] }} — {{ $house['resident'] }}</span>
                                    <span class="block text-[12px] text-[#64748B]">{{ $house['address'] ?? $house['estate'] ?? '-' }}</span>
                                </span>
                                @if ($house['billed'])
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-bold text-amber-800">Sudah ditagih</span>
                                @endif
                            </label>
                        @empty
                            <p class="py-6 text-center text-[14px] text-[#64748B]">Tidak ada rumah aktif yang cocok.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white disabled:opacity-60">
                    <i data-lucide="calendar-plus" class="h-5 w-5"></i>
                    <span wire:loading.remove wire:target="generate">Generate Sekarang</span>
                    <span wire:loading wire:target="generate">Memproses...</span>
                </button>
            </div>
        </form>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Rumah Aktif</p>
            <p class="mt-1 text-2xl font-bold">{{ $activeHouses }}</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Sudah Ditagih</p>
            <p class="mt-1 text-2xl font-bold">{{ $alreadyBilled }}</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Perkiraan Tagihan Baru</p>
            <p class="mt-1 text-2xl font-bold">{{ max(0, $activeHouses - $alreadyBilled) }}</p>
        </div>
    </div>

    @if ($rate)
        <x-ui.alert type="info" icon="tags">
            Tarif dipakai: <span class="font-semibold">{{ $rate->name }}</span> — @rupiah($rate->amount) ({{ $rate->periodLabel() }})
        </x-ui.alert>
    @endif

    @if ($result)
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="font-bold">Hasil Generate</p>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-emerald-50 p-3">
                    <p class="text-[12px] font-semibold text-emerald-700">Dibuat</p>
                    <p class="text-xl font-bold text-emerald-800">{{ $result['created'] }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 p-3">
                    <p class="text-[12px] font-semibold text-sky-700">Dipulihkan</p>
                    <p class="text-xl font-bold text-sky-800">{{ $result['restored'] }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3">
                    <p class="text-[12px] font-semibold text-amber-700">Dilewati</p>
                    <p class="text-xl font-bold text-amber-800">{{ $result['skipped'] }}</p>
                </div>
                <div class="rounded-xl bg-slate-100 p-3">
                    <p class="text-[12px] font-semibold text-slate-700">Tanpa Tarif</p>
                    <p class="text-xl font-bold text-slate-800">{{ $result['no_rate'] }}</p>
                </div>
            </div>
            <p class="mt-3 text-[14px] text-[#64748B]">
                Total nilai tagihan diproses: <span class="font-bold text-[#0F172A]">@rupiah($result['total'])</span>
            </p>
            <a href="{{ route('admin.ipl.billings.index') }}" wire:navigate
                class="mt-4 flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] font-semibold">
                <i data-lucide="file-text" class="h-4 w-4"></i> Lihat Daftar Tagihan
            </a>
        </div>
    @endif

    {{-- <x-ui.alert type="info" icon="shield-check">
        Anti-duplikat aktif: kombinasi <span class="font-semibold">rumah + bulan + tahun + tarif</span> bersifat unik di database,
        sehingga generate berulang pada periode & tarif yang sama tidak akan membuat tagihan ganda.
    </x-ui.alert> --}}
</div>