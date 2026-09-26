@props(['icon' => 'inbox', 'title' => 'Belum ada data', 'subtitle' => ''])

<div class="rounded-[22px] bg-white p-8 text-center shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-[18px] bg-gradient-to-br from-teal-50 to-teal-100">
        <i data-lucide="{{ $icon }}" class="h-6 w-6 text-teal-700"></i>
    </div>
    <p class="font-semibold">{{ $title }}</p>
    @if ($subtitle)
        <p class="mt-1 text-[14px] text-[#64748B]">{{ $subtitle }}</p>
    @endif
    @if (trim($slot ?? '') !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
