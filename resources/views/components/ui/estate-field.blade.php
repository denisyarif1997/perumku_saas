{{--
    Field "Perumahan" untuk halaman admin.

    Hanya pengguna platform (super_admin) yang perlu memilih dari beberapa
    perumahan. Admin estate selalu terikat ke satu perumahan, jadi dropdown-nya
    tidak berguna dan bisa menyiratkan bahwa mereka boleh memilih — diganti
    menjadi label statis.

    Ini murni tampilan. Penulisan tetap dikunci di level model oleh hook
    BelongsToEstate::applyEstateWriteScope(), jadi tetap aman.
--}}
@props([
    'estates',
    'label' => 'Perumahan',
    'model' => 'housing_estate_id',
    'error' => null,
    'live' => false,
    'placeholder' => 'Semua Perumahan',
    'showCode' => false,
    'hint' => 'Otomatis memakai perumahan ini.',
    'inputClass' => 'min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]',
])

@php
    $canChoose = $estates->count() > 1;
    $own = $estates->first();
@endphp

<label class="block">
    <span class="mb-1.5 block text-[14px] font-medium">{{ $label }}</span>

    @if ($canChoose)
        <select wire:model{{ $live ? '.live' : '' }}="{{ $model }}" class="{{ $inputClass }}">
            <option value="">{{ $placeholder }}</option>
            @foreach ($estates as $estate)
                <option value="{{ $estate->id }}">
                    {{ $estate->name }}{{ $showCode ? ' ('.$estate->code.')' : '' }}
                </option>
            @endforeach
        </select>
    @else
        <div class="flex min-h-[48px] items-center gap-2 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[15px] text-[#475569]">
            <span class="font-medium">{{ $own?->name ?? $placeholder }}</span>
            @if ($own && $showCode)
                <span class="text-[13px] text-[#94A3B8]">{{ $own->code }}</span>
            @endif
        </div>
        @if ($own && $hint)
            <span class="mt-1 block text-[12px] text-[#94A3B8]">{{ $hint }}</span>
        @endif
    @endif

    @if ($error)
        <span class="mt-1 block text-[13px] text-red-600">{{ $error }}</span>
    @endif
</label>
