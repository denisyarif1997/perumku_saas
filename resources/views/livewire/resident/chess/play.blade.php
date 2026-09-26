@php
    $pieces = [
        'wK' => '♔', 'wQ' => '♕', 'wR' => '♖', 'wB' => '♗', 'wN' => '♘', 'wP' => '♙',
        'bK' => '♚', 'bQ' => '♛', 'bR' => '♜', 'bB' => '♝', 'bN' => '♞', 'bP' => '♟',
    ];

    $whiteName = $game->whitePlayer?->name ?? 'Putih';
    $blackName = $game->blackPlayer?->name ?? 'Hitam';

    $flipped = $myColor === 'b';
    
    $lastMove = end($moves) ?: null;
    if ($replayAt !== null && isset($moves[$replayAt - 1])) {
        $lastMove = $moves[$replayAt - 1];
    }
    $fromSq = $lastMove ? \App\Support\ChessEngine::squareIndex($lastMove['from']) : null;
    $toSq = $lastMove ? \App\Support\ChessEngine::squareIndex($lastMove['to']) : null;
@endphp

<div x-data="{ showHistory: @js($showHistory) }" class="space-y-3" wire:poll.1.5s>
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="flex items-center justify-between">
        <a href="{{ route('resident.chess.index') }}" wire:navigate
            class="inline-flex items-center gap-1 text-[14px] font-semibold text-[#64748B]">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Keluar Pertandingan
        </a>
        <button type="button" @click="showHistory = !showHistory"
            class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white p-2 text-[#64748B] hover:bg-[#F8FAFC]">
            <i data-lucide="history" class="h-5 w-5"></i>
        </button>
    </div>

    {{-- Info Pemain Hitam --}}
    <div class="flex items-center justify-between rounded-2xl border border-[#E2E8F0] bg-white px-4 py-3">
        <div class="flex items-center gap-2 text-[14px] font-semibold">
            <span class="text-[18px]">♚</span>
            {{ $blackName }}
            @if ($game->turn === 'b' && $game->isActive())
                <x-ui.badge color="emerald">Giliran Jalan</x-ui.badge>
            @endif
        </div>
        <span class="text-[12px] text-[#64748B]">vs {{ $whiteName }} ♔</span>
    </div>

    {{-- Status Banner Game --}}
    @if (! $game->isActive())
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 text-center">
            <p class="text-[16px] font-bold">
                @if ($game->winner)
                    🏆 {{ $game->winner === 'w' ? 'Putih Menang!' : 'Hitam Menang!' }}
                @else
                    🤝 Permainan Remis (Draw)
                @endif
            </p>
            <p class="mt-1 text-[13px] text-[#64748B]">{{ $game->endReasonLabel() }}</p>
        </div>
    @elseif (! $myColor)
        <div class="rounded-xl bg-sky-50 p-3 text-center text-[13px] font-semibold text-sky-700">
            Mode Penonton — Menyaksikan {{ $whiteName }} vs {{ $blackName }}.
        </div>
    @elseif ($game->turn !== $myColor)
        <div class="rounded-xl bg-amber-50 p-3 text-center text-[13px] font-semibold text-amber-700">
            Menunggu langkah lawan...
        </div>
    @else
        <div class="rounded-xl bg-emerald-50 p-3 text-center text-[13px] font-semibold text-emerald-700">
            Giliran Anda ({{ $myColor === 'w' ? 'Putih' : 'Hitam' }}) — Pilih & jalankan bidak.
        </div>
    @endif

    {{-- Indikator Skak --}}
    @if ($inCheck && $game->isActive())
        <div class="rounded-xl bg-red-50 p-2 text-center text-[13px] font-semibold text-red-700">
            ⚠️ SKAK! Raja {{ $game->turn === 'w' ? 'Putih' : 'Hitam' }} terancam!
        </div>
    @endif

    {{-- Container Papan Catur Utama --}}
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-[#E2E8F0] bg-slate-900 dark:bg-slate-900 p-2 select-none shadow-sm">

        <div class="flex items-center">
            {{-- Grid Papan 8x8 --}}
            <div class="grid aspect-square min-w-0 flex-1 grid-cols-8 grid-rows-8 overflow-hidden rounded-lg">
                @php
                    $order = $flipped ? range(63, 0, -1) : range(0, 63);
                @endphp
                @foreach ($order as $index)
                    @php
                        $piece = $board[$index] ?? null;
                        $rank = intdiv($index, 8);
                        $file = $index % 8;
                        $light = ($rank + $file) % 2 === 0;
                        $isTarget = in_array($index, $targets);
                        $isSelected = $selected === $index;
                        $isLastMove = $lastMove && ($index === $fromSq || $index === $toSq);
                    @endphp
                    <button type="button" wire:click="tapSquare({{ $index }})" style="font-size:32px;line-height:0.85"
                        class="relative flex h-full w-full min-h-0 min-w-0 items-center justify-center overflow-hidden
                            {{ $light ? 'bg-[#F0D9B5]' : 'bg-[#B58863]' }}
                            {{ $isLastMove ? 'ring-4 ring-inset ring-amber-400' : '' }}
                            {{ $isSelected ? 'ring-4 ring-inset ring-sky-500' : '' }}
                            {{ $isTarget && $piece ? 'ring-4 ring-inset ring-red-500/70' : '' }}">

                        @if ($piece)
                            <span style="font-weight:900;-webkit-text-stroke:1.5px currentColor;paint-order:stroke;{{ str_starts_with((string) $piece, 'w') ? 'color:#fff;text-shadow:0 0 2px #1e293b,0 1px 2px rgba(30,41,59,.8);' : 'color:#1e293b;text-shadow:0 1px 1px rgba(255,255,255,.4);' }}">
                                {{ $pieces[$piece] }}
                            </span>
                        @endif
                        @if ($isTarget && ! $piece)
                            <span class="absolute h-4 w-4 rounded-full bg-teal-700/30"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Drawer Histori Langkah --}}
    <div x-show="showHistory" x-cloak class="space-y-2">
        <div class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-white px-3 py-2">
            <p class="text-[13px] font-semibold">Notasi Langkah ({{ count($moves) }})</p>
            <span class="text-[11px] text-[#64748B]">
                @if ($replayAt !== null)
                    Review Langkah {{ $replayAt }}/{{ count($moves) }}
                @else
                    Live • Giliran: {{ $game->turn === 'w' ? 'Putih' : 'Hitam' }}
                @endif
            </span>
        </div>

        {{-- Kontrol Navigasi Histori --}}
        <div class="flex items-center justify-center gap-2">
            <button type="button" wire:click="replayStart" wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white p-2 text-[#64748B] hover:bg-[#F8FAFC]">
                <i data-lucide="skip-back" class="h-4 w-4"></i>
            </button>
            <button type="button" wire:click="replayPrev" wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white p-2 text-[#64748B] hover:bg-[#F8FAFC]">
                <i data-lucide="chevron-left" class="h-4 w-4"></i>
            </button>
            @if ($replayAt !== null)
                <button type="button" wire:click="replayLive" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1 rounded-xl bg-teal-700 px-3 py-2 text-[12px] font-semibold text-white">
                    <i data-lucide="play" class="h-4 w-4"></i> Kembalikan Posisi Live
                </button>
            @else
                <button type="button" wire:click="replayStart" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1 rounded-xl bg-teal-700 px-3 py-2 text-[12px] font-semibold text-white">
                    <i data-lucide="play" class="h-4 w-4"></i> Review Analisis
                </button>
            @endif
            <button type="button" wire:click="replayNext" wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white p-2 text-[#64748B] hover:bg-[#F8FAFC]">
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </button>
        </div>

        {{-- Daftar Notasi --}}
        <div class="w-full space-y-1 overflow-y-auto rounded-xl border border-[#E2E8F0] bg-white p-3 text-[13px] max-h-60">
            @forelse ($moves as $i => $move)
                @php
                    $num = $i + 1;
                    $dot = $move['color'] === 'w' ? 'bg-teal-700' : 'bg-[#B58863]';
                    $txt = $move['color'] === 'w' ? 'text-[#0F172A]' : 'text-[#B58863]';
                    $active = $replayAt !== null ? ($replayAt === $num) : ($num === count($moves));
                @endphp
                <button type="button" wire:click="replayJump({{ $num }})"
                    class="flex w-full items-center gap-1 rounded-lg px-1 py-0.5 text-left hover:bg-[#F8FAFC] {{ $active ? 'bg-amber-50 ring-1 ring-amber-300' : '' }}">
                    <span class="w-6 shrink-0 text-[12px] font-bold text-[#64748B]">{{ $num }}.</span>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $dot }}"></span>
                    <span class="{{ $txt }}">{{ $move['from'] }}</span>
                    <i data-lucide="arrow-right" class="h-3 w-3 text-[#94A3B8]"></i>
                    <span class="{{ $txt }}">{{ $move['to'] }}</span>
                    @if ($move['capture'])
                        <i data-lucide="skull" class="h-3.5 w-3.5 text-red-500"></i>
                    @endif
                </button>
            @empty
                <p class="text-[12px] text-[#64748B]">Belum ada langkah yang dimainkan.</p>
            @endforelse
        </div>
    </div>

    {{-- Penanda Mode Replay --}}
    @if ($replayAt !== null)
        <div class="rounded-xl bg-amber-50 p-2 text-center text-[12px] font-semibold text-amber-700">
            Mode Review — Menampilkan posisi langkah {{ $replayAt }} dari {{ count($moves) }} (Papan Terkunci).
        </div>
    @endif

    {{-- Tombol Menyerah --}}
    @if ($game->isActive() && $myColor)
        <button wire:click="resign" wire:confirm="Apakah Anda yakin ingin menyerah?" wire:loading.attr="disabled"
            class="flex min-h-[46px] w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 font-semibold text-red-700">
            <i data-lucide="flag" class="h-4 w-4"></i> Menyerah (Resign)
        </button>
    @endif

    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-3 text-center text-[13px] text-[#64748B]">
        Langkah Ke-{{ $game->fullmove }} · Sinkronisasi Otomatis Realtime
    </div>
</div>