<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[22px] font-bold">Info & Pengumuman</h1>
            <p class="text-[14px] text-[#64748B]">Informasi terbaru dari pengelola</p>
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
        <button wire:click="$set('categoryFilter', '')"
            class="whitespace-nowrap rounded-full px-4 py-2 text-[14px] font-semibold transition {{ $categoryFilter === '' ? 'bg-teal-700 text-white shadow-md shadow-teal-700/30' : 'bg-white text-[#64748B] shadow-sm' }}">Semua</button>
        @foreach (['maintenance' => 'Pemeliharaan', 'event' => 'Acara', 'security' => 'Keamanan', 'billing' => 'Tagihan', 'urgent' => 'Darurat', 'general' => 'Umum'] as $key => $label)
            <button wire:click="$set('categoryFilter', '{{ $key }}')"
                class="whitespace-nowrap rounded-full px-4 py-2 text-[14px] font-semibold transition {{ $categoryFilter === $key ? 'bg-teal-700 text-white shadow-md shadow-teal-700/30' : 'bg-white text-[#64748B] shadow-sm' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($announcements as $announcement)
            <article class="rounded-[22px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge color="{{ $announcement->priorityColor() }}">{{ $announcement->categoryLabel() }}</x-ui.badge>
                        @if ($announcement->is_pinned)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700"><i data-lucide="pin" class="h-3 w-3"></i> Disematkan</span>
                        @endif
                    </div>
                    <time class="whitespace-nowrap text-[12px] text-[#64748B]">{{ $announcement->published_at?->format('d M Y') ?? $announcement->created_at->format('d M Y') }}</time>
                </div>
                <h2 class="mt-2 text-[16px] font-bold leading-snug">{{ $announcement->title }}</h2>
                <p class="mt-1 whitespace-pre-line text-[14px] leading-relaxed text-[#64748B]">{{ $announcement->content }}</p>
                @if ($announcement->author)
                    <p class="mt-3 text-[12px] text-[#64748B]">Oleh: {{ $announcement->author->name }}</p>
                @endif
            </article>
        @empty
            <x-ui.empty-state icon="megaphone" title="Belum ada pengumuman" subtitle="Pengumuman akan muncul di sini." />
        @endforelse
    </div>

    <div>{{ $announcements->links() }}</div>
</div>