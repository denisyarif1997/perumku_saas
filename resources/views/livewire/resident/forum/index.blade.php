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
                {{ $summary['total'] }} diskusi · {{ $summary['today'] }} hari ini
            </p>
        </div>
        <button type="button" wire:click="openForm"
            class="flex min-h-[40px] shrink-0 items-center gap-1.5 rounded-xl bg-teal-700 px-3.5 text-[13px] font-semibold text-white">
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

    {{-- List thread ala Kaskus --}}
    <div class="divide-y divide-[#E2E8F0] rounded-xl border border-[#E2E8F0] bg-white">
        @forelse ($posts as $post)
            <div class="relative flex gap-3 px-3.5 py-3">
                <a href="{{ route('resident.forum.show', $post) }}" wire:navigate class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold text-[#64748B]">
                        @if ($post->is_pinned)
                            <i data-lucide="pin" class="h-3 w-3 text-sky-600"></i>
                        @endif
                        <span class="{{ $post->categoryColor() === 'default' ? 'text-teal-700' : 'text-'.$post->categoryColor().'-700' }}">
                            {{ $post->categoryLabel() }}
                        </span>
                        @if ($post->is_poll)
                            <span class="inline-flex items-center gap-0.5 text-purple-700">
                                <i data-lucide="bar-chart-3" class="h-3 w-3"></i> {{ $post->isPollOpen() ? 'Polling' : 'Polling tutup' }}
                            </span>
                        @endif
                    </div>

                    <h2 class="mt-0.5 truncate text-[14.5px] font-bold leading-snug text-[#0F172A]">
                        {{ $post->title }}
                    </h2>

                    <p class="mt-1 text-[12px] text-[#64748B]">
                        {{ $post->authorName() }}@if ($house = $post->authorHouseLabel()) · Rumah {{ $house }} @endif
                        · {{ $post->created_at->diffForHumans() }}
                    </p>

                    @if ($post->is_poll)
                        <div class="mt-2 rounded-xl bg-purple-50 p-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[12px] font-semibold text-purple-900">
                                    {{ $post->pollTypeLabel() }} · {{ $post->isPollOpen() ? 'Berlangsung' : 'Ditutup' }}
                                </p>
                                <p class="shrink-0 text-[12px] text-purple-800">{{ $post->poll_voters_count ?? 0 }} warga sudah memilih</p>
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach ($post->pollOptions() as $optionLabel)
                                    <span class="rounded-lg bg-white px-2 py-0.5 text-[12px] text-purple-900">{{ $optionLabel }}</span>
                                @endforeach
                            </div>
                            @if ($post->isPollOpen())
                                <p class="mt-1.5 flex items-center gap-1 text-[12px] font-semibold text-purple-800">
                                    Ikut Polling <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                </p>
                            @endif
                        </div>
                    @endif
                </a>

                <div class="flex shrink-0 flex-col items-end justify-between">
                    <span class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#64748B]">
                        <i data-lucide="message-circle" class="h-3.5 w-3.5"></i>
                        {{ $post->comments_count ?? 0 }}
                    </span>
                    @can('delete', $post)
                        <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="Hapus postingan ini beserta komentarnya?"
                            class="mt-1 flex h-7 w-7 items-center justify-center rounded-lg text-red-500" title="Hapus">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                        </button>
                    @endcan
                </div>
            </div>
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

    {{-- Modal composer: buat diskusi baru atau polling baru --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            wire:click.self="closeForm">
            <div class="flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-[24px] bg-white sm:rounded-[24px]">
                <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-4 py-3">
                    <p class="font-bold text-[#134E4A]">Buat Postingan</p>
                    <button type="button" wire:click="closeForm" aria-label="Tutup form"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#E2E8F0]">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <form wire:submit="submit" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <x-ui.field label="Judul" :error="$errors->first('title')">
                        <input wire:model="title" maxlength="150" placeholder="Contoh: air keran keruh sejak kemarin"
                            class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px] outline-none focus:border-teal-600">
                    </x-ui.field>

                    <x-ui.field label="Isi" :error="$errors->first('body')">
                        <textarea wire:model="body" rows="4" maxlength="3000"
                            placeholder="Ceritakan lebih detail agar warga lain bisa membantu."
                            class="w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] p-3.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                    </x-ui.field>

                    <x-ui.field label="Kategori" :error="$errors->first('category')">
                        <select wire:model="category" class="min-h-[46px] w-full rounded-2xl border border-[#EEF2F1] bg-[#F6F8F7] px-3.5 text-[15px]">
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    {{-- Beralih ke mode polling --}}
                    @if ($isPoll)
                        <div class="space-y-3 rounded-2xl bg-purple-50 p-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="flex items-center gap-1.5 text-[14px] font-bold text-purple-800">
                                    <i data-lucide="bar-chart-3" class="h-4 w-4"></i> Mode Polling
                                </p>
                                <button type="button" wire:click="disablePoll" class="text-[13px] font-semibold text-purple-800 underline">
                                    Kembali ke diskusi biasa
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <select wire:model="pollType" aria-label="Cara memilih"
                                    class="min-h-[44px] w-full rounded-xl border border-purple-200 bg-white px-3 text-[14px]">
                                    <option value="single">Pilih satu</option>
                                    <option value="multiple">Pilih beberapa</option>
                                </select>
                                <select wire:model="pollDuration" aria-label="Berlaku sampai"
                                    class="min-h-[44px] w-full rounded-xl border border-purple-200 bg-white px-3 text-[14px]">
                                    <option value="1">1 hari</option>
                                    <option value="3">3 hari</option>
                                    <option value="7">7 hari</option>
                                    <option value="30">30 hari</option>
                                    <option value="0">Tanpa batas</option>
                                </select>
                            </div>

                            <div>
                                <p class="mb-1.5 text-[13px] font-medium text-purple-900">Pilihan (minimal dua, berbeda)</p>
                                <div class="space-y-2">
                                    @foreach ($pollChoices as $index => $choice)
                                        <div class="flex items-center gap-2" wire:key="poll-choice-{{ $index }}">
                                            <input wire:model="pollChoices.{{ $index }}" maxlength="100" placeholder="Pilihan {{ $index + 1 }}"
                                                class="min-h-[44px] w-full rounded-xl border border-purple-200 bg-white px-3 text-[14px] outline-none focus:border-purple-400">
                                            @if (count($pollChoices) > 2)
                                                <button type="button" wire:click="removePollChoice({{ $index }})"
                                                    aria-label="Hapus pilihan {{ $index + 1 }}"
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-red-200 text-red-600">
                                                    <i data-lucide="minus" class="h-4 w-4"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if ($errors->first('pollChoices'))
                                    <p class="mt-1 text-[13px] text-red-600">{{ $errors->first('pollChoices') }}</p>
                                @endif
                                @error('pollChoices.*')
                                    <p class="mt-1 text-[13px] text-red-600">{{ $message }}</p>
                                @enderror

                                @if (count($pollChoices) < $maxPollChoices)
                                    <button type="button" wire:click="addPollChoice"
                                        class="mt-2 inline-flex items-center gap-1 text-[13px] font-semibold text-purple-800 underline">
                                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> Tambah pilihan
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="$set('isPoll', true)"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-purple-300 bg-purple-50 py-3 text-[14px] font-semibold text-purple-800">
                            <i data-lucide="bar-chart-3" class="h-4 w-4"></i> Jadikan Polling
                        </button>
                    @endif
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button type="button" wire:click="closeForm" class="min-h-[46px] rounded-2xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                            class="min-h-[46px] rounded-2xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Bagikan</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
</div>
