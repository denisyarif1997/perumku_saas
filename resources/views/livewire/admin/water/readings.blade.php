<div class="space-y-4">

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    @if (session('info'))
        <x-ui.alert type="info" icon="info">
            {{ session('info') }}
        </x-ui.alert>
    @endif


    {{-- FORM CATAT METER --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">

        <p class="font-bold">Catat Meter Air</p>

        <p class="mt-1 text-[14px] text-[#64748B]">
            Meter awal otomatis dari bacaan bulan lalu.
            Isi meter akhir + foto meteran, lalu Simpan Bacaan (draft) —
            tagihan baru dibuat saat Generate.
        </p>

        <form wire:submit="generate" class="mt-4 grid gap-3 md:grid-cols-2">

            <x-ui.field
                label="Bulan"
                :error="$errors->first('period_month')"
            >
                <select
                    wire:model.live="period_month"
                    class="min-h-[48px] w-full rounded-xl border bg-white px-3 text-[15px]"
                >
                    @foreach (\App\Support\Currency::MONTHS as $n => $l)
                        <option value="{{ $n }}">
                            {{ $l }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>


            <x-ui.field
                label="Tahun"
                :error="$errors->first('period_year')"
            >
                <select
                    wire:model.live="period_year"
                    class="min-h-[48px] w-full rounded-xl border bg-white px-3 text-[15px]"
                >
                    @foreach (range(now()->year - 2, now()->year + 1) as $y)
                        <option value="{{ $y }}">
                            {{ $y }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>


            <x-ui.estate-field
                :estates="$estates"
                :error="$errors->first('housing_estate_id')"
                live
                hint=""
            />


            <x-ui.field
                label="Tarif Air"
                :error="$errors->first('water_rate_id')"
            >
                <select
                    wire:model.live="water_rate_id"
                    class="min-h-[48px] w-full rounded-xl border bg-white px-3 text-[15px]"
                >
                    <option value="">
                        Otomatis (tarif berlaku)
                    </option>

                    @foreach ($rates as $o)
                        <option value="{{ $o->id }}">
                            {{ $o->name }} — @rupiah($o->price_per_m3)/m³
                        </option>
                    @endforeach
                </select>
            </x-ui.field>


            <x-ui.field
                label="Cari Rumah"
                :error="$errors->first('houseSearch')"
            >
                <input
                    wire:model.live.debounce.300ms="houseSearch"
                    placeholder="Nomor/alamat..."
                    class="min-h-[48px] w-full rounded-xl border px-3 text-[15px]"
                >
            </x-ui.field>


            <x-ui.field
                label="Jatuh Tempo (tgl)"
                :error="$errors->first('due_day')"
            >
                <input
                    wire:model.live="due_day"
                    type="number"
                    min="1"
                    max="28"
                    class="min-h-[48px] w-full rounded-xl border px-3 text-[15px]"
                >
            </x-ui.field>


            <div class="md:col-span-2 grid gap-2 sm:grid-cols-2">

                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save, photos.*"
                    class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl border border-[#0F172A] font-semibold text-[#0F172A] disabled:opacity-60"
                >
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Simpan Bacaan (Draft)
                </button>


                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="generate, photos.*"
                    class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white disabled:opacity-60"
                >
                    <i data-lucide="file-plus-2" class="h-4 w-4"></i>
                    Generate Tagihan Air
                </button>

            </div>

        </form>
    </div>


    {{-- TARIF --}}
    @if ($rate)

        <x-ui.alert type="info" icon="droplets">
            Tarif:
            <b>{{ $rate->name }}</b>
            —
            @rupiah($rate->price_per_m3)/m³
            +
            admin
            @rupiah($rate->admin_fee)
        </x-ui.alert>

    @endif


    {{-- LIST RUMAH --}}
    <div class="space-y-2">

        @forelse ($houses as $h)

            <div
                class="rounded-2xl border border-[#E2E8F0] bg-white p-4"
                x-data="{
                    zoom: null,
                    preview: null
                }"
            >

                {{-- HEADER RUMAH --}}
                <div class="flex items-center justify-between gap-2">

                    <div class="min-w-0">

                        <p class="font-bold">
                            Rumah {{ $h['label'] }}
                        </p>

                        <p class="truncate text-[13px] text-[#64748B]">
                            {{ $h['resident'] }}
                            ·
                            {{ $h['estate'] }}
                        </p>

                    </div>


                    @if ($h['billed'])

                        <x-ui.badge color="green">
                            Sudah Ditagih
                        </x-ui.badge>

                    @elseif ($h['saved'] === 'draft')

                        <x-ui.badge color="amber">
                            Draft
                        </x-ui.badge>

                    @endif

                </div>


                {{-- INFORMASI BACaan --}}
                @if ($h['saved_end'] !== null)

                    <p class="mt-1 text-[13px] text-[#64748B]">

                        {{ $h['billed'] ? 'Sudah ditagih' : 'Draft tersimpan' }}

                        —
                        akhir {{ $h['saved_end'] }} m³

                        ·
                        pakai {{ $h['saved_usage'] }} m³

                        ·
                        total @rupiah($h['saved_amount'])

                    </p>

                @endif


                {{-- METER --}}
                <div class="mt-3 grid grid-cols-2 gap-2">

                    <div class="rounded-xl bg-slate-50 p-3 text-[13px]">

                        <label class="text-[#64748B]">
                            Meter Awal
                            <span class="text-[11px]">(bisa diubah)</span>
                        </label>

                        <input
                            wire:model="starts.{{ $h['id'] }}"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="{{ $h['last'] }}"
                            class="mt-1 min-h-[40px] w-full rounded-lg border border-[#E2E8F0] bg-white px-2 text-[14px] font-bold"
                        >

                        @error('starts.'.$h['id'])

                            <p class="mt-1 text-[12px] text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    <div>

                        <label class="text-[13px] text-[#64748B]">
                            Meter Akhir
                        </label>

                        <input
                            wire:model="meters.{{ $h['id'] }}"
                            type="number"
                            min="{{ $h['last'] }}"
                            step="0.01"
                            placeholder="{{ $h['last'] }}"
                            class="mt-1 min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px]"
                        >

                    </div>


                    {{-- UPLOAD FOTO --}}
                    <div class="col-span-2">

                        <label class="text-[13px] text-[#64748B]">
                            Foto Meteran
                            (JPG/PNG/WebP)
                        </label>

                        <input
                            type="file"
                            wire:model="photos.{{ $h['id'] }}"
                            accept="image/jpeg,image/png,image/webp"
                            @change="
                                preview = $event.target.files && $event.target.files[0]
                                    ? URL.createObjectURL($event.target.files[0])
                                    : null
                            "
                            class="mt-1 w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[14px] file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-[13px] file:font-semibold"
                        >


                        @error('photos.'.$h['id'])

                            <p class="mt-1 text-[12px] text-red-600">
                                {{ $message }}
                            </p>

                        @enderror


                        <div
                            wire:loading
                            wire:target="photos.{{ $h['id'] }}"
                            class="mt-1 text-[12px] font-semibold text-amber-600"
                        >
                            <i
                                data-lucide="loader-circle"
                                class="mr-1 inline h-3.5 w-3.5 animate-spin"
                            ></i>

                            Mengunggah foto...
                        </div>

                    </div>


                    {{-- FOTO BARU YANG DIPILIH --}}
                    @if (isset($photos[$h['id']]))

                        <div
                            class="col-span-2 flex items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50 p-2"
                        >

                            <button
                                type="button"
                                @click="zoom = 'preview'"
                                title="Klik untuk melihat foto"
                                class="shrink-0"
                            >

                                <img
                                    :src="
                                        preview ??
                                        '{{ $photos[$h['id']] instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                            ? $photos[$h['id']]->temporaryUrl()
                                            : '' }}'
                                    "
                                    alt="Pratinjau foto meteran"
                                    class="h-16 w-16 cursor-zoom-in rounded-lg border border-emerald-200 object-cover"
                                >

                            </button>


                            <div class="min-w-0 flex-1">

                                <p class="text-[13px] font-semibold text-emerald-800">
                                    Pratinjau foto terpilih
                                </p>

                                <p class="text-[12px] text-emerald-700">
                                    Klik "Simpan Bacaan" agar foto ikut tersimpan
                                </p>

                            </div>


                            <div class="flex items-center gap-2">

                                <button
                                    type="button"
                                    @click="zoom = 'preview'"
                                    class="rounded-lg border border-emerald-300 px-3 py-2 text-[12px] font-semibold text-emerald-700"
                                >
                                    Lihat
                                </button>


                                <button
                                    type="button"
                                    @click="preview = null"
                                    wire:click="clearSelectedPhoto({{ $h['id'] }})"
                                    wire:confirm="Hapus foto meteran yang baru dipilih?"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-[12px] font-semibold text-red-700"
                                >
                                    Hapus
                                </button>

                            </div>

                        </div>

                    @elseif (! $h['photo_url'])

                        {{-- PREVIEW DARI FILE YANG BARU DIPILIH --}}
                        <div
                            x-show="preview"
                            x-cloak
                            class="col-span-2 flex items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50 p-2"
                        >

                            <button
                                type="button"
                                @click="zoom = 'preview'"
                                title="Klik untuk melihat foto"
                                class="shrink-0"
                            >

                                <img
                                    :src="preview"
                                    alt="Pratinjau foto meteran"
                                    class="h-16 w-16 cursor-zoom-in rounded-lg border border-emerald-200 object-cover"
                                >

                            </button>


                            <div class="min-w-0 flex-1">

                                <p class="text-[13px] font-semibold text-emerald-800">
                                    Pratinjau foto terpilih
                                </p>

                                <p class="text-[12px] text-emerald-700">
                                    Klik "Simpan Bacaan" agar foto ikut tersimpan
                                </p>

                            </div>


                            <button
                                type="button"
                                @click="zoom = 'preview'"
                                class="rounded-lg border border-emerald-300 px-3 py-2 text-[12px] font-semibold text-emerald-700"
                            >
                                Lihat
                            </button>

                        </div>

                    @endif


                    {{-- FOTO TERSIMPAN --}}
                    @if ($h['photo_url'])

                        <div
                            class="col-span-2 flex items-center gap-3 rounded-xl bg-slate-50 p-2"
                        >

                            <button
                                type="button"
                                @click="zoom = 'current'"
                                title="Klik untuk melihat foto"
                                class="shrink-0"
                            >

                                <img
                                    src="{{ $h['photo_url'] }}"
                                    alt="Foto meteran {{ $h['label'] }}"
                                    class="h-16 w-16 cursor-zoom-in rounded-lg border border-[#E2E8F0] object-cover"
                                    loading="lazy"
                                >

                            </button>


                            <div class="min-w-0 flex-1">

                                <p class="text-[13px] font-semibold">
                                    Foto meteran tersimpan
                                </p>

                                <p class="text-[12px] text-[#64748B]">
                                    {{ $h['billed'] ? 'Terkunci (sudah ditagih)' : 'Akan ikut saat tagihan dibuat' }}
                                    · klik foto untuk melihat
                                </p>

                            </div>


                            <div class="flex items-center gap-2">

                                <button
                                    type="button"
                                    @click="zoom = 'current'"
                                    class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-[12px] font-semibold"
                                >
                                    Lihat
                                </button>


                                @unless ($h['billed'])

                                    <button
                                        type="button"
                                        wire:click="removePhoto({{ $h['id'] }})"
                                        wire:confirm="Hapus foto meteran ini?"
                                        class="rounded-lg border border-red-200 px-3 py-2 text-[12px] font-semibold text-red-700"
                                    >
                                        Hapus
                                    </button>

                                @endunless

                            </div>

                        </div>

                    @endif


                    {{-- FOTO SEBELUMNYA --}}
                    @if ($h['last_photo_url'] && ! $h['photo_url'])

                        <div
                            class="col-span-2 flex items-center gap-3 rounded-xl border border-dashed border-[#E2E8F0] p-2"
                        >

                            <button
                                type="button"
                                @click="zoom = 'last'"
                                title="Klik untuk melihat foto"
                                class="shrink-0"
                            >

                                <img
                                    src="{{ $h['last_photo_url'] }}"
                                    alt="Foto bacaan sebelumnya {{ $h['label'] }}"
                                    class="h-12 w-12 cursor-zoom-in rounded-lg border border-[#E2E8F0] object-cover"
                                    loading="lazy"
                                >

                            </button>


                            <div>

                                <p class="text-[13px] font-semibold">
                                    Foto bacaan sebelumnya
                                </p>

                                <p class="text-[12px] text-[#64748B]">
                                    Periode {{ $h['last_period'] }}
                                    · klik foto untuk melihat
                                </p>

                            </div>

                        </div>

                    @endif

                </div>


                {{-- MODAL FOTO --}}
                <div
                    x-show="zoom"
                    x-cloak
                    x-transition.opacity
                    @keydown.escape.window="zoom = null"
                    @click.self="zoom = null"
                    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4"
                >

                    <div
                        class="relative max-h-[90dvh] w-full max-w-4xl overflow-auto rounded-2xl bg-white p-3 shadow-2xl"
                        @click.stop
                    >

                        <div class="mb-3 flex items-center justify-between gap-3">

                            <p class="text-[13px] font-bold">
                                Foto Meteran — Rumah {{ $h['label'] }}
                            </p>

                            <button
                                type="button"
                                @click="zoom = null"
                                class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-[13px] font-semibold text-[#64748B]"
                            >
                                Tutup
                            </button>

                        </div>


                        {{-- FOTO TERSIMPAN --}}
                        @if ($h['photo_url'])

                            <template x-if="zoom === 'current'">

                                <img
                                    src="{{ $h['photo_url'] }}"
                                    alt="Foto meteran {{ $h['label'] }}"
                                    class="max-h-[75dvh] w-full rounded-xl object-contain"
                                >

                            </template>

                        @endif


                        {{-- FOTO PREVIEW --}}
                        <template x-if="zoom === 'preview'">

                            <img
                                :src="preview"
                                alt="Pratinjau foto meteran"
                                class="max-h-[75dvh] w-full rounded-xl object-contain"
                            >

                        </template>


                        {{-- FOTO SEBELUMNYA --}}
                        @if ($h['last_photo_url'])

                            <template x-if="zoom === 'last'">

                                <img
                                    src="{{ $h['last_photo_url'] }}"
                                    alt="Foto meteran sebelumnya {{ $h['label'] }}"
                                    class="max-h-[75dvh] w-full rounded-xl object-contain"
                                >

                            </template>

                        @endif

                    </div>

                </div>

            </div>

        @empty

            <x-ui.empty-state
                icon="droplets"
                title="Tidak ada rumah"
                subtitle="Ubah filter perumahan/pencarian."
            />

        @endforelse

    </div>


    {{-- HASIL GENERATE --}}
    @if ($result)

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">

            <p class="font-bold">
                Hasil:
                dibuat {{ $result['created'] }},
                dipulihkan {{ $result['restored'] }},
                sudah ditagih {{ $result['skipped'] }},
                invalid {{ $result['invalid'] }},
                draft tersimpan {{ $result['drafts_saved'] }}
            </p>

        </div>

    @endif

</div>