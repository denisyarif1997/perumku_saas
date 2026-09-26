<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('info'))
        <x-ui.alert type="info" icon="info">{{ session('info') }}</x-ui.alert>
    @endif

    <a href="{{ route('resident.complaints.index') }}" wire:navigate class="inline-flex items-center gap-2 text-[14px] font-semibold text-[#64748B]">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali ke pengaduan
    </a>

    {{-- Ringkasan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-mono text-[12px] text-[#64748B]">{{ $complaint->ticket_number }}</p>
                <p class="mt-1 text-[18px] font-bold">{{ $complaint->title }}</p>
            </div>
            <x-ui.badge color="{{ $complaint->statusColor() }}">{{ $complaint->statusLabel() }}</x-ui.badge>
        </div>

        <p class="mt-3 whitespace-pre-line text-[14px] text-[#64748B]">{{ $complaint->description }}</p>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-[14px]">
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
            @if ($complaint->resolved_at)
                <div>
                    <dt class="text-[#64748B]">Selesai</dt>
                    <dd class="font-semibold text-emerald-700">{{ $complaint->resolved_at->format('d/m/Y H:i') }}</dd>
                </div>
            @endif
        </dl>

        @if ($complaint->resolution_note)
            <div class="mt-3 rounded-xl bg-emerald-50 p-3">
                <p class="text-[12px] font-semibold text-emerald-700">Catatan Penyelesaian</p>
                <p class="mt-1 text-[14px] text-emerald-900">{{ $complaint->resolution_note }}</p>
            </div>
        @endif

        @if (! in_array($complaint->status, ['resolved', 'closed'], true))
            <button wire:click="confirmResolved" wire:confirm="Tandai pengaduan ini sebagai selesai?"
                class="mt-4 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 font-semibold text-emerald-700">
                <i data-lucide="check-circle-2" class="h-4 w-4"></i> Sudah Teratasi
            </button>
        @endif
    </div>

    {{-- Riwayat tanggapan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Riwayat Tanggapan</p>

        <div class="mt-3 space-y-3">
            @forelse ($complaint->responses as $response)
                <div class="rounded-xl border border-[#E2E8F0] p-3 {{ $response->is_internal ? 'bg-amber-50' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[14px] font-bold">{{ $response->authorName() }}</p>
                        <span class="text-[12px] text-[#64748B]">{{ $response->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-[14px] text-[#64748B]">{{ $response->message }}</p>
                    @if ($response->is_internal)
                        <p class="mt-1 text-[12px] font-semibold text-amber-700">Catatan internal</p>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="message-circle" title="Belum ada tanggapan" subtitle="Tanggapan pengelola akan tampil di sini." />
            @endforelse
        </div>

        <form wire:submit="addResponse" class="mt-4 space-y-2">
            <x-ui.field label="Tambah Tanggapan" :error="$errors->first('message')">
                <textarea wire:model="message" rows="3" placeholder="Tulis pesan tambahan..."
                    class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-teal-600"></textarea>
            </x-ui.field>
            <button type="submit" wire:loading.attr="disabled"
                class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                <i data-lucide="send" class="h-4 w-4"></i> Kirim
            </button>
        </form>
    </div>
</div>