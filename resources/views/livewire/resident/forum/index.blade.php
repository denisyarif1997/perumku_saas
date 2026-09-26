<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Header + statistik ringkas --}}
    <div class="rounded-2xl bg-teal-700 p-4 text-white">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-[20px] font-bold leading-tight">Forum Warga</h1>
                <p class="mt-0.5 text-[13px] text-white/70">Diskusi, tanya jawab, dan kabar antar warga</p>
            </div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">
                <i data-lucide="messages-square" class="h-5 w-5"></i>
            </span>
        </div>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-white/10 p-2.5">
                <p class="text-[18px] font-bold">{{ $summary['total'] }}</p>
                <p class="text-[11px] text-white/70">Diskusi</p>
            </div>
            <div class="rounded-xl bg-white/10 p-2.5">
                <p class="text-[18px] font-bold">{{ $summary['today'] }}</p>
                <p class="text-[11px] text-white/70">Hari ini</p>
            </div>
            <div class="rounded-xl bg-white/10 p-2.5">
                <p class="text-[18px] font-bold">{{ $summary['mine'] }}</p>
                <p class="text-[11px] text-white/70">Milik saya</p>
            </div>
        </div>
    </div>

    {{-- Komposer cepat ala sosmed --}}
    <button type="button" wire:click="openForm"
        class="flex w-full items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-3 text-left active:bg-slate-50">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[14px] font-bold text-white">
            {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
        </span>
        <span class="min-w-0 flex-1 truncate rounded-full bg-slate-100 px-4 py-2.5 text-[14px] text-[#64748B]">
            Apa yang ingin dibagikan ke warga?
        </span>
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-700 text-white">
            <i data-lucide="plus" class="h-5 w-5"></i>
        </span>
    </button>

    {{-- Pencarian + urutan --}}
    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari diskusi..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-10 pr-3 text-[15px] outline-none focus:border-teal-600">
        </div>
        <select wire:model.live="sortBy" aria-label="Urutkan"
            class="min-h-[48px] w-[128px] shrink-0 rounded-2xl border border-[#E2E8F0] bg-white px-2 text-[14px]">
            <option value="latest">Terbaru</option>
            <option value="discussed">Ramai</option>
        </select>
    </div>

    {{-- Chip kategori --}}
    <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button type="button" wire:click="setCategory('')"
            class="shrink-0 rounded-full px-3.5 py-2 text-[13px] font-semibold transition {{ $categoryFilter === '' ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
            Semua
        </button>
        @foreach ($categories as $value => $label)
            <button type="button" wire:click="setCategory('{{ $value }}')"
                class="shrink-0 rounded-full px-3.5 py-2 text-[13px] font-semibold transition {{ $categoryFilter === $value ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Chip ruang lingkup + status filter --}}
    <div class="flex gap-2">
        <button type="button" wire:click="setScope('')"
            class="flex-1 rounded-xl px-2 py-2.5 text-[13px] font-semibold transition {{ $scopeFilter === '' ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
            Semua
        </button>
        <button type="button" wire:click="setScope('mine')"
            class="flex-1 rounded-xl px-2 py-2.5 text-[13px] font-semibold transition {{ $scopeFilter === 'mine' ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
            Milik saya
        </button>
        <button type="button" wire:click="setScope('today')"
            class="flex-1 rounded-xl px-2 py-2.5 text-[13px] font-semibold transition {{ $scopeFilter === 'today' ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
            Hari ini
        </button>
        <button type="button" wire:click="setScope('pinned')"
            class="flex-1 rounded-xl px-2 py-2.5 text-[13px] font-semibold transition {{ $scopeFilter === 'pinned' ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
            Disematkan
        </button>
    </div>

    @if ($isFiltering)
        <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-100 px-3 py-2 text-[13px]">
            <span class="min-w-0 flex-1 truncate text-[#64748B]">Filter aktif{{ $search ? ': “'.$search.'”' : '' }}</span>
            <button type="button" wire:click="resetFilter" class="shrink-0 font-semibold underline">Reset</button>
        </div>
    @endif

    <div wire:loading.flex wire:target="search,categoryFilter,scopeFilter,sortBy" class="items-center gap-2 text-[13px] text-[#64748B]">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-teal-700"></span>
        Memuat diskusi...
    </div>

    {{-- Feed diskusi --}}
    <div class="space-y-3">
        @forelse ($posts as $post)
            <article class="rounded-2xl border border-[#E2E8F0] bg-white p-4 transition active:bg-slate-50 {{ $post->is_pinned ? 'ring-1 ring-sky-200' : '' }}">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[14px] font-bold text-slate-700">
                        {{ strtoupper(substr($post->authorName(), 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-semibold">{{ $post->authorName() }}</p>
                        <p class="truncate text-[12px] text-[#64748B]">
                            @if ($house = $post->authorHouseLabel()) Rumah {{ $house }} · @endif{{ $post->created_at->diffForHumans() }}
                        </p>
                    </div>
                    @if ($post->is_pinned)
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-700" title="Disematkan">
                            <i data-lucide="pin" class="h-4 w-4"></i>
                        </span>
                    @endif
                </div>

                <a href="{{ route('resident.forum.show', $post) }}" wire:navigate class="mt-2.5 block">
                    <h2 class="text-[16px] font-bold leading-snug">{{ $post->title }}</h2>
                    <p class="mt-1 line-clamp-3 text-[14px] leading-relaxed text-[#64748B]">{{ $post->body }}</p>
                </a>

                <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                    <x-ui.badge color="{{ $post->categoryColor() }}">{{ $post->categoryLabel() }}</x-ui.badge>
                    <span class="inline-flex items-center gap-1 text-[12px] text-[#64748B]">
                        <i data-lucide="message-circle" class="h-3.5 w-3.5"></i>
                        {{ $post->comments_count ?? 0 }} komentar
                    </span>
                </div>

                <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                    <a href="{{ route('resident.forum.show', $post) }}" wire:navigate
                        class="flex min-h-[42px] flex-1 items-center justify-center gap-1.5 rounded-xl bg-slate-100 text-[14px] font-semibold text-[#0F172A]">
                        <i data-lucide="message-circle" class="h-4 w-4"></i> Diskusi
                    </a>
                    @can('delete', $post)
                        <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="Hapus postingan ini beserta komentarnya?"
                            class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-red-200 text-red-700" title="Hapus postingan">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    @endcan
                </div>
            </article>
        @empty
            <x-ui.empty-state icon="messages-square" title="Belum ada diskusi" subtitle="Jadilah yang pertama membuka diskusi di forum warga.">
                @if (! $isFiltering)
                    <button wire:click="openForm" class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 font-semibold text-white">
                        <i data-lucide="plus" class="h-4 w-4"></i> Buat Postingan Pertama
                    </button>
                @else
                    <button wire:click="resetFilter" class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] px-6 font-semibold">
                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset filter
                    </button>
                @endif
            </x-ui.empty-state>
        @endforelse
    </div>

    <div>{{ $posts->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div wire:click="closeForm" class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                <div class="flex shrink-0 items-center justify-between gap-3 p-5 pb-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[14px] font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-[16px] font-bold leading-tight">Postingan Baru</p>
                            <p class="text-[12px] text-[#64748B]">Bagikan ke semua warga</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeForm" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E2E8F0]">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form wire:submit="submit" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <div>
                        <div class="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                            @foreach ($categories as $value => $label)
                                <button type="button" wire:click="$set('category', '{{ $value }}')"
                                    class="shrink-0 rounded-full px-3.5 py-2 text-[13px] font-semibold transition {{ $category === $value ? 'bg-teal-700 text-white' : 'border border-[#E2E8F0] bg-white text-[#64748B]' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        @error('category') <p class="mt-1 text-[13px] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-ui.field label="Judul" :error="$errors->first('title')">
                        <input wire:model="title" maxlength="150" placeholder="Contoh: Gotong royong akhir pekan"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-teal-600">
                        <p class="mt-1 text-right text-[12px] text-[#64748B]">{{ mb_strlen($title) }}/150</p>
                    </x-ui.field>

                    <x-ui.field label="Isi Postingan" :error="$errors->first('body')">
                        <textarea wire:model.live.debounce.200ms="body" rows="6" maxlength="3000" placeholder="Ceritakan sesuatu yang ingin dibagikan ke warga..."
                            class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                        <p class="mt-1 text-right text-[12px] text-[#64748B]">{{ mb_strlen($body) }}/3000</p>
                    </x-ui.field>

                    <div class="grid grid-cols-2 gap-2 pb-safe">
                        <button type="button" wire:click="closeForm"
                            class="flex min-h-[48px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Bagikan</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
