@props(['color' => 'slate'])

@php
    $styles = [
        'slate' => 'bg-slate-100 text-slate-700',
        'green' => 'bg-emerald-50 text-emerald-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'purple' => 'bg-purple-50 text-purple-700',
        'pink' => 'bg-pink-50 text-pink-700',
        'red' => 'bg-red-50 text-red-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'sky' => 'bg-sky-50 text-sky-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ' . ($styles[$color] ?? $styles['slate'])]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $slot }}
</span>
