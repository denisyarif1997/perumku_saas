<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold">Pengaduan Saya</h1>
            <p class="text-[14px] text-[#64748B]">Sampaikan keluhan atau laporan kerusakan</p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-sky-700">{{ $summary['open'] }}</p>
            <p class="text-[12px] text-[#64748B]">Baru</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-amber-700">{{ $summary['progress'] }}</p>
            <p class="text-[12px] text-[#64748B]">Diproses</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-emerald-700">{{ $summary['resolved'] }}</p>
            <p class="text-[12px] text-[#64748B]">Selesai</p>
        </div>
    </div>

    <button wire:click="openForm" class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white">
        <i data-lucide="plus" class="h-5 w-5"></i> Buat Pengaduan
    </button>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach (['' => 'Semua', 'open' => 'Baru', 'in_progress' => 'Diproses', 'resolved' => 'Selesai', 'closed' => 'Ditutup'] as $key => $label)
            <button wire:click="$set('statusFilter', '{{ $key }}')" class="whitespace-nowrap rounded-full px-4 py-2 text-[14px] font-semibold {{ $statusFilter === $key ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($complaints as $complaint)
            <a href="{{ route('resident.complaints.show', $complaint) }}" wire:navigate class="block rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 flex-1 truncate text-[16px] font-bold">{{ $complaint->title }}</p>
                    <x-ui.badge color="{{ $complaint->statusColor() }}">{{ $complaint->statusLabel() }}</x-ui.badge>
                </div>
                <p class="mt-0.5 font-mono text-[12px] text-[#64748B]">{{ $complaint->ticket_number }}</p>
                <p class="mt-1 line-clamp-2 text-[14px] text-[#64748B]">{{ $complaint->description }}</p>
                <div class="mt-4 border-t border-[#E2E8F0] pt-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[13px] font-semibold">Rumah {{ $complaint->house?->fullLabel() ?? '-' }}</p>
                        <p class="text-[12px] text-[#64748B]">{{ $complaint->created_at->format('d/m/Y') }}</p>
                    </div>
                </div>
            </a>
        @empty
            <x-ui.empty-state icon="message-square" title="Belum ada pengaduan" subtitle="Pengaduan yang Anda buat akan tampil di sini.">
                <button wire:click="openForm" class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 font-semibold text-white">
                    <i data-lucide="plus" class="h-4 w-4"></i> Buat Pengaduan Pertama
                </button>
            </x-ui.empty-state>
        @endforelse
    </div>

    <div>{{ $complaints->links() }}</div>


    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div wire:click="closeForm" class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                <div class="flex shrink-0 items-center justify-between p-5 pb-3">
                    <p class="text-lg font-bold">Pengaduan Baru</p>
                    <button wire:click="closeForm" class="flex h-10 w-10 items-center justify-center rounded-xl border"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <form wire:submit="submit" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <x-ui.field label="Judul" :error="$errors->first('title')">
                        <input wire:model="title" placeholder="Ringkasan masalah"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-teal-600">
                    </x-ui.field>
                    <x-ui.field label="Kategori" :error="$errors->first('category')">
                        <select wire:model="category" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            <option value="general">Umum</option>
                            <option value="water">Air</option>
                            <option value="electricity">Listrik</option>
                            <option value="security">Keamanan</option>
                            <option value="cleanliness">Kebersihan</option>
                            <option value="facility">Fasilitas</option>
                            <option value="neighbor">Tetangga</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Prioritas" :error="$errors->first('priority')">
                        <select wire:model="priority" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            <option value="low">Rendah</option>
                            <option value="normal">Normal</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Deskripsi" :error="$errors->first('description')">
                        <textarea wire:model="description" rows="4" placeholder="Jelaskan masalahnya..."
                            class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                    </x-ui.field>
                    <div class="grid grid-cols-2 gap-2 pb-safe">
                        <button type="button" wire:click="closeForm"
                            class="flex min-h-[48px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Kirim</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>