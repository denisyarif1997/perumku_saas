<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <a href="{{ route('admin.info.complaints') }}" wire:navigate class="inline-flex items-center gap-2 text-[14px] font-semibold text-[#64748B]">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali ke daftar komplain
    </a>

    {{-- Ringkasan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-mono text-[12px] text-[#64748B]">{{ $complaint->ticket_number }}</p>
                <p class="mt-1 text-lg font-bold">{{ $complaint->title }}</p>
                <p class="text-[14px] text-[#64748B]">{{ $complaint->resident?->name ?? 'Tanpa pelapor' }} · {{ $complaint->house?->fullLabel() ?? '-' }}</p>
            </div>
            <x-ui.badge color="{{ $complaint->statusColor() }}">{{ $complaint->statusLabel() }}</x-ui.badge>
        </div>

        <p class="mt-3 whitespace-pre-line text-[14px] text-[#64748B]">{{ $complaint->description }}</p>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-[14px] md:grid-cols-4">
            <div>
                <dt class="text-[#64748B]">Kategori</dt>
                <dd class="font-semibold">{{ $complaint->categoryLabel() }}</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Prioritas</dt>
                <dd class="font-semibold">{{ $complaint->priorityLabel() }}</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Dibuat</dt>
                <dd class="font-semibold">{{ $complaint->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Ditangani Oleh</dt>
                <dd class="font-semibold">{{ $complaint->assignee?->name ?? 'Belum ditugaskan' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Form status & penugasan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Status & Penugasan</p>

        <form wire:submit="updateStatus" class="mt-3 grid gap-3 md:grid-cols-2">
            <x-ui.field label="Status" :error="$errors->first('status')">
                <select wire:model="status" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="open">Baru</option>
                    <option value="in_progress">Diproses</option>
                    <option value="resolved">Selesai</option>
                    <option value="closed">Ditutup</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Petugas" :error="$errors->first('assigned_to')">
                <select wire:model="assigned_to" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="">Belum ditugaskan</option>
                    @foreach ($staff as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <div class="md:col-span-2">
                <x-ui.field label="Catatan Penyelesaian (opsional)" :error="$errors->first('resolution_note')">
                    <textarea wire:model="resolution_note" rows="2" placeholder="Diisi saat menyelesaikan pengaduan..."
                        class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-[#0F172A]"></textarea>
                </x-ui.field>
            </div>

            <div class="md:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white disabled:opacity-60 md:w-auto">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    {{-- Form tanggapan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Kirim Tanggapan</p>

        <form wire:submit="saveResponse" class="mt-3 space-y-3">
            <x-ui.field label="Pesan Tanggapan" :error="$errors->first('response_message')">
                <textarea wire:model="response_message" rows="3" placeholder="Tulis tanggapan untuk warga..."
                    class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-[#0F172A]"></textarea>
            </x-ui.field>

            <label class="flex items-center gap-3 text-[14px]">
                <input wire:model="is_internal" type="checkbox" class="h-5 w-5 rounded border-[#E2E8F0]">
                <span>
                    <span class="font-semibold">Catatan internal</span>
                    <span class="text-[#64748B]">— tidak terlihat oleh warga</span>
                </span>
            </label>

            <button type="submit" wire:loading.attr="disabled"
                class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white disabled:opacity-60 md:w-auto">
                <i data-lucide="send" class="h-4 w-4"></i> Kirim Tanggapan
            </button>
        </form>
    </div>

    {{-- Riwayat tanggapan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Riwayat Tanggapan</p>

        <div class="mt-3 space-y-3">
            @forelse ($complaint->responses as $response)
                <div class="rounded-xl border border-[#E2E8F0] p-3 {{ $response->is_internal ? 'bg-amber-50' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[14px] font-bold">{{ $response->authorName() }}
                            @if ($response->isFromStaff())
                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-[#64748B]">Staf</span>
                            @endif
                        </p>
                        <span class="text-[12px] text-[#64748B]">{{ $response->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-[14px] text-[#64748B]">{{ $response->message }}</p>
                    @if ($response->is_internal)
                        <p class="mt-1 text-[12px] font-semibold text-amber-700">Hanya terlihat oleh pengelola</p>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="message-circle" title="Belum ada tanggapan" subtitle="Jadilah yang pertama menanggapi." />
            @endforelse
        </div>
    </div>
</div>