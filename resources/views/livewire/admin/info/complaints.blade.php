@php
    use App\Support\Currency;
@endphp

<div class="space-y-4">
    {{-- Alert messages --}}
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Ringkasan KPI --}}
    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-sky-700">{{ $summary['open'] }}</p>
            <p class="text-[12px] text-[#64748B]">Baru</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-amber-700">{{ $summary['inProgress'] }}</p>
            <p class="text-[12px] text-[#64748B]">Diproses</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-emerald-700">{{ $summary['closed'] }}</p>
            <p class="text-[12px] text-[#64748B]">Selesai</p>
        </div>
    </div>

    {{-- Pencarian & Filter --}}
    <div class="space-y-2">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nomor tiket / judul / warga..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <select wire:model.live="statusFilter"
                class="min-h-[44px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[14px]">
                <option value="">Semua Status</option>
                <option value="open">Baru</option>
                <option value="in_progress">Diproses</option>
                <option value="resolved">Selesai</option>
                <option value="closed">Ditutup</option>
            </select>

            <select wire:model.live="categoryFilter"
                class="min-h-[44px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[14px]">
                <option value="">Semua Kategori</option>
                <option value="general">Umum</option>
                <option value="water">Air</option>
                <option value="electricity">Listrik</option>
                <option value="security">Keamanan</option>
                <option value="cleanliness">Kebersihan</option>
                <option value="facility">Fasilitas</option>
                <option value="neighbor">Tetangga</option>
            </select>

            <select wire:model.live="priorityFilter"
                class="min-h-[44px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[14px]">
                <option value="">Semua Prioritas</option>
                <option value="low">Rendah</option>
                <option value="normal">Normal</option>
                <option value="high">Tinggi</option>
            </select>
        </div>

        @if ($search || $statusFilter || $categoryFilter || $priorityFilter)
            <button wire:click="resetFilter" class="text-[13px] font-semibold text-[#64748B]">Reset Filter</button>
            @endif
    </div>

    {{-- Mobile list --}}
    <div class="space-y-2 md:hidden">
        @forelse ($complaints as $complaint)
            <a href="{{ route('admin.info.complaints.show', $complaint) }}" wire:navigate
                class="block rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-[12px] text-[#64748B]">{{ $complaint->ticket_number }}</p>
                        <p class="mt-0.5 text-[16px] font-bold">{{ Str::limit($complaint->title, 50) }}</p>
                        <p class="mt-1 line-clamp-2 text-[13px] text-[#64748B]">{{ Str::limit($complaint->description, 80) }}</p>
                        <p class="mt-2 text-[12px] text-[#64748B]">
                            {{ $complaint->resident?->name ?? 'Tanpa pelapor' }} · {{ $complaint->house?->fullLabel() ?? '-' }}
                        </p>
                    </div>
                    <x-ui.badge color="{{ $complaint->statusColor() }}">{{ $complaint->statusLabel() }}</x-ui.badge>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                    <x-ui.badge color="{{ $complaint->priorityColor() }}">{{ $complaint->priorityLabel() }}</x-ui.badge>
                    <x-ui.badge color="slate">{{ $complaint->categoryLabel() }}</x-ui.badge>
                </div>
            </a>
        @empty
            <x-ui.empty-state icon="message-square" title="Belum ada komplain" subtitle="Komplain warga akan tampil di sini." />
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Tiket</th>
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Warga</th>
                    <th class="px-4 py-3">Rumah</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Prioritas</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($complaints as $complaint)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-mono text-[13px] text-[#64748B]">{{ $complaint->ticket_number }}</td>
                        <td class="px-4 py-3 font-semibold">{{ Str::limit($complaint->title, 50) }}</td>
                        <td class="px-4 py-3">{{ $complaint->resident?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $complaint->house?->fullLabel() ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $complaint->categoryLabel() }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $complaint->priorityColor() }}">{{ $complaint->priorityLabel() }}</x-ui.badge></td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $complaint->statusColor() }}">{{ $complaint->statusLabel() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.info.complaints.show', $complaint) }}" wire:navigate class="font-semibold text-sky-700">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-[#64748B]">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div>{{ $complaints->links() }}</div>
</div>

