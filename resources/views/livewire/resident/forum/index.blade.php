    {{-- List thread ala Kaskus --}}
    <div class="divide-y divide-[#E2E8F0] rounded-xl border border-[#E2E8F0] bg-white">
        @forelse ($posts as $post)
            <div class="group relative flex items-start justify-between gap-3 px-3.5 py-3 hover:bg-slate-50">
                {{-- Hitbox Klik Utama yang Membungkus Konten Kiri --}}
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

                {{-- Kolom Aksi Kanan (Terpisah secara terisolasi dari tag <a>) --}}
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
