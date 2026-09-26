@props(['label', 'error' => null])

<label class="block">
    <span class="mb-1.5 block text-[14px] font-medium">{{ $label }}</span>
    {{ $slot }}
    @if ($error)
        <span class="mt-1 block text-[13px] text-red-600">{{ $error }}</span>
    @endif
</label>
