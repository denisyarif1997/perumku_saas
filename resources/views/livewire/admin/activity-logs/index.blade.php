<div class="space-y-4">
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
        <input wire:model.live.debounce.300ms="search" placeholder="Cari aktivitas..."
            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 outline-none focus:border-[#0F172A]">
    </div>
    <div class="space-y-2">
        @forelse ($logs as $log)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <p class="text-[14px] font-semibold">{{ $log->description }}</p>
                <p class="mt-1 text-[13px] text-[#64748B]">{{ $log->user?->name ?? 'System' }} • {{ $log->module }} • {{ $log->created_at->format('d M Y H:i') }}</p>
            </div>
        @empty
            <x-ui.empty-state icon="scroll-text" title="Belum ada aktivitas" />
        @endforelse
    </div>
    <div>{{ $logs->links() }}</div>
</div>
