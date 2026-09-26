<div class="space-y-4" wire:poll.3s>
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div>
        <h1 class="text-[22px] font-bold">Catur Warga</h1>
        <p class="text-[14px] text-[#64748B]">Ajak warga lain main catur secara langsung</p>
    </div>

    {{-- Undangan personal untuk saya --}}
    @foreach ($invites as $invite)
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-[18px]">♟️</div>
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] font-semibold">{{ $invite->inviter?->name ?? 'Warga' }}</p>
                    <p class="text-[13px] text-[#64748B]">Mengundang Anda bermain catur</p>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button wire:click="decline({{ $invite->id }})" wire:loading.attr="disabled"
                    class="flex min-h-[44px] items-center justify-center rounded-xl border border-[#E2E8F0] font-semibold">
                    Tolak
                </button>
                <button wire:click="accept({{ $invite->id }})" wire:loading.attr="disabled"
                    class="flex min-h-[44px] items-center justify-center rounded-xl bg-teal-700 font-semibold text-white">
                    Terima
                </button>
            </div>
        </div>
    @endforeach
    {{-- Permainan saya yang sedang berlangsung --}}
    @if ($myGames->isNotEmpty())
        <div>
            <h2 class="mb-2 text-[16px] font-bold">Permainan Berlangsung</h2>
            <div class="space-y-2">
                @foreach ($myGames as $game)
                    <a href="{{ route('resident.chess.play', $game) }}" wire:navigate
                        class="flex items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-[18px]">♟️</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[14px] font-semibold">
                                {{ $game->whitePlayer?->name ?? '-' }} <span class="text-[#64748B]">vs</span> {{ $game->blackPlayer?->name ?? '-' }}
                            </p>
                            <p class="text-[13px] text-[#64748B]">Giliran: {{ $game->turn === 'w' ? 'Putih' : 'Hitam' }}</p>
                        </div>
                        <x-ui.badge color="emerald">Lanjut</x-ui.badge>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tantangan terbuka --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[14px] font-semibold">Tantangan Terbuka</p>
                <p class="text-[13px] text-[#64748B]">Tantangan ini bisa diterima warga mana pun.</p>
            </div>
            @if ($myPendingId)
                <button wire:click="cancelPending" wire:loading.attr="disabled"
                    class="flex min-h-[40px] items-center gap-1 rounded-xl border border-red-200 px-3 text-[13px] font-semibold text-red-700">
                    <i data-lucide="x" class="h-4 w-4"></i> Batalkan
                </button>
            @endif
        </div>

        @if ($myPendingId)
            <div class="mt-3 rounded-xl bg-amber-50 p-3 text-[13px] font-semibold text-amber-700">
                Menunggu warga lain menerima tantangan Anda...
            </div>
        @else
            <button wire:click="createOpenChallenge" wire:loading.attr="disabled"
                class="mt-3 flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white">
                <i data-lucide="swords" class="h-5 w-5"></i> Buat Tantangan Terbuka
            </button>
        @endif

        @forelse ($openChallenges as $challenge)
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-[#E2E8F0] p-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] font-semibold">{{ $challenge->inviter?->name ?? 'Warga' }}</p>
                    <p class="text-[12px] text-[#64748B]">Membuat tantangan terbuka</p>
                </div>
                <button wire:click="accept({{ $challenge->id }})" wire:loading.attr="disabled"
                    class="flex min-h-[40px] items-center rounded-xl bg-teal-700 px-4 text-[13px] font-semibold text-white">
                    Terima
                </button>
            </div>
        @empty
    {{-- Undang warga tertentu --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="text-[14px] font-semibold">Undang Warga</p>
        <div class="mt-3 space-y-2">
            @forelse ($residents as $resident)
                <div class="flex items-center gap-3 rounded-xl border border-[#E2E8F0] p-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-[13px] font-bold">
                        {{ strtoupper(substr($resident->name, 0, 1)) }}
                    </div>
                    <p class="min-w-0 flex-1 truncate text-[14px] font-medium">{{ $resident->name }}</p>
                    <button wire:click="invite({{ $resident->id }})" wire:loading.attr="disabled"
                        class="flex min-h-[40px] items-center gap-1 rounded-xl border border-[#E2E8F0] px-3 text-[13px] font-semibold">
                        <i data-lucide="send" class="h-4 w-4"></i> Undang
                    </button>
                </div>
            @empty
                <p class="text-[13px] text-[#64748B]">Belum ada warga lain dengan akun aktif.</p>
            @endforelse
        </div>
    </div>

    {{-- Permainan warga lain (publik — tonton & lihat histori) --}}
    @if ($publicGames->isNotEmpty())
        <div>
            <h2 class="mb-2 text-[16px] font-bold">Sedang Dimainkan Warga</h2>
            <div class="space-y-2">
                @foreach ($publicGames as $game)
                    <a href="{{ route('resident.chess.play', $game) }}" wire:navigate
                        class="flex items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-[18px]">👁️</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[14px] font-semibold">
                                {{ $game->whitePlayer?->name ?? '-' }} <span class="text-[#64748B]">vs</span> {{ $game->blackPlayer?->name ?? '-' }}
                            </p>
                            <p class="text-[13px] text-[#64748B]">
                                {{ count($game->moves ?? []) }} langkah ·
                                {{ $game->status === 'active' ? 'Giliran: '.($game->turn === 'w' ? 'Putih' : 'Hitam') : $game->endReasonLabel() }}
                            </p>
                        </div>
                        <x-ui.badge color="{{ $game->status === 'active' ? 'sky' : 'slate' }}">
                            {{ $game->status === 'active' ? 'Tonton' : 'Histori' }}
                        </x-ui.badge>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Riwayat permainan --}}
    @if ($history->isNotEmpty())
        <div>
            <h2 class="mb-2 text-[16px] font-bold">Riwayat</h2>
            <div class="space-y-2">
                @foreach ($history as $game)
                    <div class="flex items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-[14px] font-semibold">
                                {{ $game->whitePlayer?->name ?? '-' }} vs {{ $game->blackPlayer?->name ?? '-' }}
                            </p>
                            <p class="text-[13px] text-[#64748B]">{{ $game->endReasonLabel() }}</p>
                        </div>
                        @if ($game->winner)
                            <x-ui.badge color="{{ $game->winner === 'w' ? 'sky' : 'purple' }}">
                                Menang {{ $game->winner === 'w' ? 'Putih' : 'Hitam' }}
                            </x-ui.badge>
                        @else
                            <x-ui.badge color="slate">Remis</x-ui.badge>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

            @if (! $myPendingId)
                <p class="mt-3 text-[13px] text-[#64748B]">Belum ada tantangan terbuka.</p>
            @endif
        @endforelse
    </div>

