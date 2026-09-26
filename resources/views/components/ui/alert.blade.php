@props(['type' => 'info', 'icon' => null])

@php
    $styles = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'danger' => 'border-red-200 bg-red-50 text-red-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-2xl border-0 p-4 text-[14px] shadow-sm ' . ($styles[$type] ?? $styles['info'])]) }}>
    @if ($icon)
        <i data-lucide="{{ $icon }}" class="mt-0.5 h-5 w-5 shrink-0"></i>
    @endif
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
