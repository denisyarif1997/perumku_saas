<div class="space-y-3">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Header ringkas --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[19px] font-bold leading-tight text-[#0F172A]">Forum Warga</h1>
            <p class="text-[12px] text-[#64748B]">
                {{ $summary['total'] ?? 0 }} diskusi · {{ $summary['today'] ?? 0 }} hari ini
            </p>
        </div>
        <button type="button" wire:click="openForm"
            class="flex min-h-[40px] shrink-0 items-center gap-1.5 rounded-xl bg-teal-700 px-3.5 text-[13px] font-semibold text-white hover:bg-teal-800 active:scale-95">
            <i data-lucide="plus" class="h-4 w-4"></i> Diskusi
        </button>
    </div>

    {{-- Komposer cepat: satu area klik --}}
    <button type="button" wire:click="openForm"
        class="flex w-full items-center gap-2.5 rounded-xl border border-[#E2E8F0] bg-white px-3 py-2.5 text-left active:bg-slate-50">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[13px] font-bold text-white">
            {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
        </span>
        <span class="min-w-0 flex-1 truncate text-[13px] text-[#94A3B8]">
            Apa yang ingin dibagikan ke warga?
        </span>
    </button>

    {{-- Tab kategori --}}
    <div class="no-scrollbar -mx-4 flex gap-4 overflow-x-auto border-b border-[#E2E8F0] px-4">
        <button type="button" wire:click="setCategory('')"
            class="shrink-0 border-b-2 pb-2 text-[13px] font-semibold {{ $categoryFilter === '' ? 'border-teal-700 text-teal-700' : 'border-transparent text-[#64748B]' }}">
            Semua
        </button>
        @foreach ($categories as $value => $label)
            <button type="button" wire:click="setCategory('{{ $value }}')"
                class="shrink-0 border-b-2 pb-2 text-[13px] font-semibold {{ $categoryFilter === $value ? 'border-teal-700 text-teal-700' : 'border-transparent text-[#64748B]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Pencarian + dua dropdown ringkas --}}
    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"></i>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari diskusi..."
                class="min-h-[42px] w-full rounded-xl border border-[#E2E8F0] bg-white pl-9 pr-3 text-[14px] outline-none focus:border-teal-600">
        </div>
        <select wire:model.live="scopeFilter" aria-label="Tampilkan"
            class="min-h-[42px] w-[110px] shrink-0 rounded-xl border border-[#E2E8F0] bg-white px-2 text-[13px]">
            <option value="">Semua</option>
            <option value="mine">Milik saya</option>
            <option value="today">Hari ini</option>
            <option value="pinned">Disematkan</option>
        </select>
        <select wire:model.live="sortBy" aria-label="Urutkan"
            class="min-h-[42px] w-[96px] shrink-0 rounded-xl border border-[#E2E8F0] bg-white px-2 text-[13px]">
            <option value="latest">Terbaru</option>
            <option value="discussed">Ramai</option>
        </select>
    </div>

    @if ($isFiltering)
        <div class="flex items-center justify-between gap-2 text-[12px]">
            <span class="min-w-0 flex-1 truncate text-[#64748B]">Filter aktif{{ $search ? ': “'.$search.'”' : '' }}</span>
            <button type="button" wire:click="resetFilter" class="shrink-0 font-semibold text-teal-700 underline">Reset</button>
        </div>
    @endif

    <div wire:loading.flex wire:target="search,categoryFilter,scopeFilter,sortBy" class="items-center gap-2 text-[13px] text-[#64748B]">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-teal-700"></span>
        Memuat...
    </div>

    {{-- List thread --}}
    <div class="divide-y divide-[#E2E8F0] rounded-xl border border-[#E2E8F0] bg-white">
        @forelse ($posts as $post)
            <div class="group relative flex items-start justify-between gap-3 px-3.5 py-3 hover:bg-slate-50">
                {{-- Link detail postingan --}}
                <a href="{{ route('resident.forum.show', $post) }}" wire:navigate class="block min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#64748B]">
                        @if ($post->is_pinned)
                            <i data-lucide="pin" class="h-3 w-3 text-sky-600"></i>
                        @endif
                        <span class="text-teal-700">
                            {{ $post->categoryLabel() }}
                        </span>
                        @if ($post->is_poll)
                            <span class="inline-flex items-center gap-0.5 text-purple-700">
                                <i data-lucide="bar-chart-3" class="h-3 w-3"></i> {{ $post->isPollOpen() ? 'Polling' : 'Polling tutup' }}
                            </span>
                        @endif
                    </div>

                    <h2 class="mt-0.5 truncate text-[14.5px] font-bold leading-snug text-[#0F172A] group-hover:text-teal-700">
                        {{ $post->title }}
                    </h2>

                    <p class="mt-1 text-[12px] text-[#64748B]">
                        {{ $post->authorName() }}@if ($house = $post->authorHouseLabel()) · Rumah {{ $house }} @endif
                        · {{ $post->created_at->diffForHumans() }}
                        @if ($post->is_poll)
                            · {{ $post->poll_voters_count ?? 0 }} memilih
                        @endif
                    </p>
                </a>

                {{-- Kolom Aksi Kanan --}}
                <div class="relative z-10 flex shrink-0 flex-col items-end justify-between self-stretch pl-2">
                    <span class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#64748B]">
                        <i data-lucide="message-circle" class="h-3.5 w-3.5"></i>
                        {{ $post->comments_count ?? 0 }}
                    </span>
                    @can('delete', $post)
                        <button type="button" 
                            wire:click.stop="delete({{ $post->id }})" 
                            wire:confirm="Hapus postingan ini beserta komentarnya?"
                            class="mt-1 flex h-7 w-7 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 active:scale-95" 
                            title="Hapus">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                        </button>
                    @endcan
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="messages-square" title="Belum ada diskusi" subtitle="Jadilah yang pertama membuka diskusi di forum warga.">
                <div class="mt-2 flex w-full flex-col gap-2">
                    <button type="button" wire:click="openForm" class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 font-semibold text-white hover:bg-teal-800 active:scale-95">
                        <i data-lucide="plus" class="h-4 w-4"></i> Buat Postingan Pertama
                    </button>
                    @if ($isFiltering)
                        <button type="button" wire:click="resetFilter" class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] px-6 font-semibold text-[#64748B] hover:bg-slate-50">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset filter
                        </button>
                    @endif
                </div>
            </x-ui.empty-state>
        @endforelse
    </div>

    <div>{{ $posts->links() }}</div>

    {{-- Modal form --}}
    @if ($showForm)
        {{-- ... isi modal form ... --}}
    @endif
</div>
