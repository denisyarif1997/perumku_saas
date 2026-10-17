
<div class="space-y-6">
    {{-- HEADER --}}
    <div>
        <h1 class="text-2xl font-bold text-[#134E4A]">
            Alarm Darurat
        </h1>

        <p class="text-sm text-[#64748B]">
            Tekan tombol di bawah saat Anda membutuhkan bantuan segera.
            Alarm beserta keterangan Anda akan muncul sebagai notifikasi
            ke akun warga yang lain.
        </p>

        <button
            type="button"
            data-push-enable
            class="mt-3 inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition active:scale-95"
        >
            <i data-lucide="bell-ring" class="h-4 w-4"></i>
            Aktifkan Notifikasi Darurat
        </button>
    </div>

    {{-- NOTIFIKASI SUKSES --}}
    @if (session()->has('success'))
        <div class="rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- FORM KIRIM ALARM --}}
    <form wire:submit.prevent="fire">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
            <div>
                <label
                    for="panic-reason"
                    class="text-sm font-medium text-[#334155]"
                >
                    Keterangan
                    <span class="text-red-500">*</span>
                </label>

                <textarea
                    id="panic-reason"
                    wire:model="reason"
                    rows="3"
                    maxlength="500"
                    required
                    class="mt-1 w-full rounded-xl border border-[#E2E8F0] px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-300/40"
                    placeholder="Contoh: Kebakaran di Blok A No. 3, butuh bantuan!"
                ></textarea>

                @error('reason')
                    <span class="mt-1 block text-xs text-red-600">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="fire"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 px-5 text-sm font-semibold text-white shadow transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <i
                        data-lucide="alert-triangle"
                        class="h-5 w-5"
                    ></i>

                    <span wire:loading.remove wire:target="fire">
                        Kirim Alarm
                    </span>

                    <span wire:loading wire:target="fire">
                        Mengirim...
                    </span>
                </button>
            </div>
        </div>
    </form>

    {{-- ALARM TERKIRIM MILIK SENDIRI --}}
    <div>
        <h2 class="text-lg font-bold text-[#134E4A]">
            Alarm Terkirim
            <span class="text-sm font-medium text-[#64748B]">
                ({{ $count }} aktif)
            </span>
        </h2>

        @if (count($open) === 0)
            <div class="mt-3 rounded-2xl border border-[#E2E8F0] bg-white p-4 text-sm text-[#64748B]">
                Belum ada alarm darurat terbuka. Setiap alarm yang
                terkirim akan tampil di sini.
            </div>
        @else
            <div class="mt-3 space-y-3">
                @foreach ($open as $alarm)
                    <div
                        wire:key="my-panic-{{ $alarm->id }}"
                        class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                                <i
                                    data-lucide="alert-triangle"
                                    class="h-5 w-5"
                                ></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#134E4A]">
                                    {{ $alarm->reason ?: 'Alarm darurat tanpa alasan' }}
                                </p>

                                <p class="mt-1 text-xs text-[#64748B]">
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 font-medium uppercase text-red-600">
                                        {{ $alarm->status }}
                                    </span>

                                    &middot;

                                    {{ $alarm->created_at?->format('d M Y H:i') ?? '' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-[#64748B]">
                            <p>
                                Warga:
                                {{ $alarm->resident?->name ?? '-' }}
                            </p>

                            <p>
                                Pengirim:
                                {{ $alarm->user?->name ?? '-' }}
                            </p>

                            <p class="flex items-center gap-1">
                                <i data-lucide="clock" class="h-3 w-3"></i>
                                Dikirim
                                {{ $this->formatTimeAgo($alarm->created_at) }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- RIWAYAT ALARM SELURUH WARGA SATU PERUMAHAN --}}
    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-[#134E4A]">
                    Riwayat Alarm Warga
                </h2>

                <p class="mt-1 text-xs text-[#64748B]">
                    Riwayat alarm darurat warga di perumahan yang sama.
                </p>
            </div>

            <span class="shrink-0 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">
                {{ $history->total() }} riwayat
            </span>
        </div>

        @if ($history->isEmpty())
            <div class="mt-3 rounded-2xl border border-[#E2E8F0] bg-white p-6 text-center">
                <i
                    data-lucide="history"
                    class="mx-auto h-8 w-8 text-[#94A3B8]"
                ></i>

                <p class="mt-2 text-sm font-semibold text-[#134E4A]">
                    Belum ada riwayat alarm
                </p>

                <p class="mt-1 text-xs text-[#64748B]">
                    Alarm yang dikirim warga perumahan ini akan tampil di sini.
                </p>
            </div>
        @else
            <div class="mt-3 space-y-3">
                @foreach ($history as $alarm)
                    <article
                        wire:key="panic-history-{{ $alarm->id }}"
                        class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm"
                    >
                        {{-- JUDUL DAN STATUS --}}
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                {{ $alarm->status === 'open'
                                    ? 'bg-red-50 text-red-600'
                                    : 'bg-teal-50 text-teal-700' }}"
                            >
                                <i
                                    data-lucide="{{ $alarm->status === 'open' ? 'alert-triangle' : 'check-circle' }}"
                                    class="h-5 w-5"
                                ></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-semibold text-[#134E4A]">
                                    {{ $alarm->reason ?: 'Alarm darurat tanpa alasan' }}
                                </p>

                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase
                                        {{ $alarm->status === 'open'
                                            ? 'bg-red-50 text-red-600'
                                            : 'bg-teal-50 text-teal-700' }}"
                                    >
                                        {{ $alarm->status === 'open' ? 'Darurat' : 'Selesai' }}
                                    </span>

                                    <span class="text-xs text-[#64748B]">
                                        {{ $alarm->created_at?->format('d M Y, H:i') ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- INFORMASI WARGA --}}
                        <div class="mt-3 space-y-2 border-t border-[#F1F5F9] pt-3 text-xs text-[#64748B]">
                            <p>
                                <span class="font-medium text-[#334155]">
                                    Warga:
                                </span>

                                {{ $alarm->resident?->name ?? '-' }}
                            </p>

                            <p>
                                <span class="font-medium text-[#334155]">
                                    Pengirim:
                                </span>

                                {{ $alarm->user?->name ?? '-' }}
                            </p>

                            <p class="flex items-center gap-1">
                                <i data-lucide="clock" class="h-3 w-3"></i>

                                Dikirim
                                {{ $this->formatTimeAgo($alarm->created_at) }}
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- PAGINATION --}}
            <div class="mt-4">
                {{ $history->links() }}
            </div>
        @endif
    </div>
</div>
