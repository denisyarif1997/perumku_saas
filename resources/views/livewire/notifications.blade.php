<div class="relative" wire:poll.15s>

    {{-- Tombol Notifikasi --}}
    <button
        type="button"
        wire:click="toggle"
        aria-label="Notifikasi"
        aria-haspopup="true"
        class="relative flex h-11 w-11 items-center justify-center transition active:scale-95
            {{ $variant === 'admin'
                ? 'border border-[#E2E8F0] hover:bg-slate-100'
                : 'bg-white text-[#134E4A] shadow-sm' }}
            {{ $variant === 'admin' ? 'rounded-xl' : 'rounded-2xl' }}"
    >
        <i data-lucide="bell" class="h-5 w-5"></i>

        @if ($unreadCount > 0)
            <span
                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white shadow"
            >
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>


    {{-- ========================================================= --}}
    {{-- DROPDOWN NOTIFIKASI --}}
    {{-- ========================================================= --}}
    @if ($open)

        {{-- Wrapper full layar --}}
        <div
            class="fixed inset-x-0 z-[9999] flex justify-center px-3"
            style="top: 64px;"
            @click.outside="$wire.close()"
        >

            {{-- Panel --}}
            <div
                class="w-full max-w-[340px] overflow-hidden rounded-[24px] border border-[#EEF2F1] bg-white p-2 shadow-[0_16px_50px_-12px_rgba(19,78,74,0.3)]"
            >

                {{-- ================================================= --}}
                {{-- HEADER --}}
                {{-- ================================================= --}}
                <div class="flex items-center justify-between px-3 pb-2 pt-1">

                    <p class="text-[14px] font-bold text-[#134E4A]">
                        Notifikasi
                    </p>

                    @if ($unreadCount > 0)

                        <button
                            type="button"
                            wire:click="markAllRead"
                            class="flex min-h-[32px] items-center gap-1 rounded-lg px-2 text-[12px] font-semibold text-teal-700 transition hover:bg-teal-50"
                        >
                            <i
                                data-lucide="check-check"
                                class="h-3.5 w-3.5"
                            ></i>

                            Tandai dibaca
                        </button>

                    @endif

                </div>


                {{-- ================================================= --}}
                {{-- TAB FILTER --}}
                {{-- ================================================= --}}
                <div
                    class="no-scrollbar flex gap-1.5 overflow-x-auto px-2 pb-2 pt-1"
                >

                    @php
                        $tabs = ['all' => 'Semua'] + $categories;
                    @endphp

                    @foreach ($tabs as $key => $label)

                        @php
                            $tabCount = $key === 'all'
                                ? $unreadCount
                                : ($unreadPerCategory[$key] ?? 0);
                        @endphp

                        <button
                            type="button"
                            wire:click="setFilter('{{ $key }}')"
                            class="flex min-h-[30px] shrink-0 items-center gap-1.5 rounded-full px-3 text-[12px] font-semibold transition
                                {{ $filter === $key
                                    ? 'bg-teal-700 text-white shadow-md shadow-teal-700/30'
                                    : 'bg-[#F1F5F9] text-[#475569] hover:bg-[#E2E8F0]' }}"
                        >

                            {{ $label }}

                            @if ($tabCount > 0)

                                <span
                                    class="flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold leading-none
                                        {{ $filter === $key
                                            ? 'bg-white text-teal-700'
                                            : 'bg-red-500 text-white' }}"
                                >
                                    {{ $tabCount > 99 ? '99+' : $tabCount }}
                                </span>

                            @endif

                        </button>

                    @endforeach

                </div>


                {{-- ================================================= --}}
                {{-- LIST NOTIFIKASI --}}
                {{-- ================================================= --}}
                <div class="max-h-[60vh] space-y-1 overflow-y-auto">

                    @forelse ($notifications as $notification)

                        @php

                            $data = $notification->data;

                            $kind = $data['kind'] ?? '';

                            $meta = match ($notification->type) {

                                \App\Notifications\NewForumPost::class => [
                                    'icon' => 'messages-square',
                                    'label' => 'Forum Baru',
                                    'tone' => 'bg-teal-50 text-teal-700',
                                ],

                                \App\Notifications\NewCommentOnPost::class => [
                                    'icon' => 'message-circle',
                                    'label' => 'Komentar Baru',
                                    'tone' => 'bg-teal-50 text-teal-700',
                                ],

                                \App\Notifications\NewComplaint::class => [
                                    'icon' => 'wrench',
                                    'label' => 'Laporan Baru',
                                    'tone' => 'bg-amber-50 text-amber-700',
                                ],

                                \App\Notifications\ComplaintUpdated::class => [
                                    'icon' => $kind === \App\Notifications\ComplaintUpdated::KIND_STATUS
                                        ? 'circle-check'
                                        : 'reply',

                                    'label' => $kind === \App\Notifications\ComplaintUpdated::KIND_STATUS
                                        ? 'Status Laporan'
                                        : 'Tanggapan Laporan',

                                    'tone' => 'bg-emerald-50 text-emerald-700',
                                ],

                                \App\Notifications\NewAnnouncement::class => [
                                    'icon' => 'megaphone',
                                    'label' => 'Pengumuman',
                                    'tone' => 'bg-sky-50 text-sky-700',
                                ],

                                default => [
                                    'icon' => 'bell',
                                    'label' => 'Notifikasi',
                                    'tone' => 'bg-slate-100 text-slate-600',
                                ],
                            };

                            $unread = ! $notification->read_at;

                        @endphp


                        {{-- ================================================= --}}
                        {{-- ITEM NOTIFIKASI --}}
                        {{-- ================================================= --}}
                        <button
                            type="button"
                            wire:key="notif-{{ $notification->id }}"
                            wire:click="openItem('{{ $notification->id }}')"
                            class="flex w-full items-start gap-3 rounded-2xl p-3 text-left transition
                                {{ $unread
                                    ? 'bg-[#F6F8F7] hover:bg-[#EEF2F1]'
                                    : 'hover:bg-[#F6F8F7]' }}"
                        >

                            {{-- Icon --}}
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $meta['tone'] }}"
                            >
                                <i
                                    data-lucide="{{ $meta['icon'] }}"
                                    class="h-4 w-4"
                                ></i>
                            </span>


                            {{-- Content --}}
                            <span class="min-w-0 flex-1">

                                {{-- Label --}}
                                <span class="flex items-center gap-1.5">

                                    <span
                                        class="text-[10px] font-semibold uppercase tracking-[0.1em]
                                            {{ $unread
                                                ? 'text-teal-700'
                                                : 'text-[#94A3B8]' }}"
                                    >
                                        {{ $meta['label'] }}
                                    </span>

                                    @if ($unread)

                                        <span
                                            class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"
                                        ></span>

                                    @endif

                                </span>


                                {{-- Title --}}
                                <span
                                    class="mt-0.5 block truncate text-[13px]
                                        {{ $unread
                                            ? 'font-semibold text-[#134E4A]'
                                            : 'font-medium text-[#334155]' }}"
                                >

                                    @isset($data['ticket_number'])
                                        {{ $data['ticket_number'] }} —
                                    @endisset

                                    {{ $data['title'] ?? '-' }}

                                </span>


                                {{-- Excerpt --}}
                                @if (! empty($data['excerpt']))

                                    <span
                                        class="mt-0.5 line-clamp-2 block text-[12px] leading-relaxed text-[#64748B]"
                                    >
                                        {{ $data['excerpt'] }}
                                    </span>

                                @endif


                                {{-- Time --}}
                                <span
                                    class="mt-1 block text-[11px] text-[#94A3B8]"
                                >

                                    {{ ($data['actor'] ?? '') !== ''
                                        ? $data['actor'] . ' · '
                                        : '' }}

                                    {{ $this->timeAgo($notification) }}

                                </span>

                            </span>

                        </button>

                    @empty

                        {{-- ================================================= --}}
                        {{-- EMPTY STATE --}}
                        {{-- ================================================= --}}
                        <div
                            class="flex flex-col items-center gap-2 px-4 py-8 text-center"
                        >

                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#F6F8F7] text-[#94A3B8]"
                            >
                                <i
                                    data-lucide="bell"
                                    class="h-5 w-5"
                                ></i>
                            </span>

                            <p
                                class="text-[13px] font-semibold text-[#134E4A]"
                            >
                                {{ $filter === 'all'
                                    ? 'Belum ada notifikasi'
                                    : 'Kategori ini masih kosong' }}
                            </p>

                            <p class="text-[12px] text-[#64748B]">
                                Update forum, laporan &amp; pengumuman akan
                                muncul di sini.
                            </p>

                        </div>

                    @endforelse

                </div>

            </div>

        </div>

    @endif

</div>