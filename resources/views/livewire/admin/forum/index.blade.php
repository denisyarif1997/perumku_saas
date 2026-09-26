<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[20px] font-bold">Moderasi Forum</h1>
            <p class="text-[14px] text-[#64748B]">Sematkan atau hapus postingan dan komentar warga</p>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-slate-800">{{ $summary['total'] }}</p>
            <p class="text-[12px] text-[#64748B]">Total Postingan</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-sky-700">{{ $summary['comments'] }}</p>
            <p class="text-[12px] text-[#64748B]">Komentar</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center">
            <p class="text-xl font-bold text-emerald-700">{{ $summary['today'] }}</p>
            <p class="text-[12px] text-[#64748B]">Hari Ini</p>
        </div>
    </div>

    {{-- Pencarian & Filter --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3">
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari judul atau isi postingan..."
                    class="min-h-[46px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </div>
            <select wire:model.live="categoryFilter" class="min-h-[46px] rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                <option value="">Semua kategori</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Daftar mobile --}}
    <div class="space-y-3 md:hidden">
        @forelse ($posts as $post)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[16px] font-bold leading-snug">{{ $post->title }}</p>
                        <p class="mt-1 text-[12px] text-[#64748B]">
                            {{ $post->authorName() }}@if ($house = $post->authorHouseLabel()) · {{ $house }}@endif
                        </p>
                    </div>
                    <x-ui.badge color="{{ $post->categoryColor() }}">{{ $post->categoryLabel() }}</x-ui.badge>
                </div>
                <p class="mt-2 line-clamp-2 text-[13px] text-[#64748B]">{{ $post->body }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[#E2E8F0] pt-3">
                    <x-ui.badge color="slate">{{ $post->comments_count }} komentar</x-ui.badge>
                    @if ($post->is_pinned)
                        <x-ui.badge color="sky"><i data-lucide="pin" class="h-3 w-3"></i> Disematkan</x-ui.badge>
                    @endif

                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" wire:click="togglePin({{ $post->id }})"
                            class="flex h-9 items-center gap-1 rounded-xl border border-[#E2E8F0] px-3 text-[12px] font-semibold">
                            <i data-lucide="pin" class="h-3.5 w-3.5"></i> {{ $post->is_pinned ? 'Lepas' : 'Sematkan' }}
                        </button>
                        <button type="button" wire:click="deletePost({{ $post->id }})"
                            wire:confirm="Hapus postingan ini beserta semua komentarnya?"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 text-red-700">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="messages-square" title="Belum ada postingan" subtitle="Postingan warga akan tampil di sini untuk dimoderasi." />
        @endforelse
    </div>

    {{-- Tabel desktop --}}
    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Postingan</th>
                    <th class="px-4 py-3">Penulis</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-center">Komentar</th>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="max-w-xs px-4 py-3">
                            <p class="font-semibold leading-snug">{{ Str::limit($post->title, 60) }}</p>
                            <p class="mt-0.5 line-clamp-1 text-[13px] text-[#64748B]">{{ Str::limit($post->body, 90) }}</p>
                            @if ($post->is_pinned)
                                <span class="mt-1 inline-block">
                                    <x-ui.badge color="sky"><i data-lucide="pin" class="h-3 w-3"></i> Disematkan</x-ui.badge>
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $post->authorName() }}
                            <span class="block text-[12px] text-[#64748B]">{{ $post->authorHouseLabel() ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $post->categoryColor() }}">{{ $post->categoryLabel() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-center">{{ $post->comments_count }}</td>
                        <td class="px-4 py-3 text-[13px] text-[#64748B]">{{ $post->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="togglePin({{ $post->id }})"
                                class="mr-2 font-semibold {{ $post->is_pinned ? 'text-amber-700' : 'text-sky-700' }}">
                                {{ $post->is_pinned ? 'Lepas Semat' : 'Sematkan' }}
                            </button>
                            <button type="button" wire:click="deletePost({{ $post->id }})"
                                wire:confirm="Hapus postingan ini beserta semua komentarnya?"
                                class="font-semibold text-red-700">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-[#64748B]">Belum ada postingan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $posts->links() }}</div>

    {{-- Moderasi komentar terbaru --}}
    @if ($recentComments->isNotEmpty())
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="font-bold">Komentar Terbaru</p>
            <div class="mt-3 space-y-2">
                @foreach ($recentComments as $comment)
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-[#E2E8F0] p-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-semibold">
                                {{ $comment->authorName() }}
                                <span class="font-normal text-[#64748B]">· pada {{ $comment->post?->title }}</span>
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-[13px] text-[#64748B]">{{ $comment->body }}</p>
                            <p class="mt-1 text-[12px] text-[#64748B]">{{ $comment->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <button type="button" wire:click="deleteComment({{ $comment->id }})"
                            wire:confirm="Hapus komentar ini?" title="Hapus komentar"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-red-200 text-red-700">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>