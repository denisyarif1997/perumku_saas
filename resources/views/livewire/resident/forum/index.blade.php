<div class="space-y-3">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Tulis status --}}
    <button type="button" wire:click="openForm"
        class="flex w-full items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-3 text-left active:bg-slate-50">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[14px] font-bold text-white">
            {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
        </span>
        <span class="min-w-0 flex-1 truncate rounded-full bg-slate-100 px-4 py-2.5 text-[14px] text-[#64748B]">
            Apa yang sedang terjadi di perumahan?
        </span>
    </button>

    {{-- Feed status --}}
    <div class="space-y-3">
        @forelse ($posts as $post)
            <article class="rounded-2xl border border-[#E2E8F0] bg-white p-4 transition active:bg-slate-50 {{ $post->is_pinned ? 'ring-1 ring-sky-200' : '' }}">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[14px] font-bold text-slate-700">
                        {{ strtoupper(substr($post->authorName(), 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-semibold">{{ $post->authorName() }}</p>
                        <p class="truncate text-[12px] text-[#64748B]">
                            @if ($house = $post->authorHouseLabel()) Rumah {{ $house }} · @endif{{ $post->created_at->diffForHumans() }}
                        </p>
                    </div>
                    @if ($post->is_pinned)
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-700" title="Disematkan">
                            <i data-lucide="pin" class="h-4 w-4"></i>
                        </span>
                    @endif
                </div>

                <a href="{{ route('resident.forum.show', $post) }}" wire:navigate class="mt-2.5 block">
                    <p class="whitespace-pre-line text-[15px] leading-relaxed">{{ $post->body }}</p>
                </a>

                <div class="mt-2.5 flex items-center gap-1.5 text-[12px] text-[#64748B]">
                    @if ($post->is_poll)
                        <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-purple-600"></i>
                        <span>Polling · {{ $post->poll_voters_count ?? 0 }} memilih</span>
                        <span>·</span>
                    @endif
                    <i data-lucide="message-circle" class="h-3.5 w-3.5"></i>
                    <span>{{ $post->comments_count ?? 0 }} komentar</span>
                </div>

                @if ($post->is_poll)
                    <p class="mt-1.5 text-[12px] text-[#64748B]">
                        Ketuk status untuk ikut polling
                    </p>
                @endif

                <div class="mt-3">
                    <a href="{{ route('resident.forum.show', $post) }}" wire:navigate
                        class="flex min-h-[42px] items-center justify-center gap-1.5 rounded-xl bg-slate-100 text-[14px] font-semibold text-[#0F172A]">
                        <i data-lucide="message-circle" class="h-4 w-4"></i>
                        Komentar
                    </a>
                </div>
            </article>
        @empty
            <x-ui.empty-state icon="messages-square" title="Belum ada status" subtitle="Jadilah yang pertama berbagi kabar.">
                <button wire:click="openForm" class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 font-semibold text-white">
                    <i data-lucide="plus" class="h-4 w-4"></i> Tulis Status Pertama
                </button>
            </x-ui.empty-state>
        @endforelse
    </div>

    <div>{{ $posts->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div wire:click="closeForm" class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                <div class="flex shrink-0 items-center justify-between gap-3 p-5 pb-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[14px] font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-[16px] font-bold leading-tight">Tulis Status</p>
                            <p class="text-[12px] text-[#64748B]">Bagikan ke semua warga</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeForm" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E2E8F0]">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form wire:submit="submit" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <x-ui.field label="Status" :error="$errors->first('body')">
                        <textarea wire:model.live.debounce.200ms="body" rows="5" maxlength="3000" placeholder="Apa yang sedang terjadi di perumahan?"
                            class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                        <p class="mt-1 text-right text-[12px] text-[#64748B]">{{ mb_strlen($body) }}/3000</p>
                    </x-ui.field>

                    {{-- Polling opsional (tersembunyi di balik 1 tombol) --}}
                    @if ($isPoll)
                    <div class="rounded-xl border border-[#E2E8F0] p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[14px] font-bold">Polling</p>
                                <button type="button" wire:click="disablePoll" class="text-[13px] font-semibold text-red-600">Batalkan</button>
                            </div>

                            <div class="mt-2 space-y-2">
                                @foreach ($pollChoices as $index => $choice)
                                    <div class="flex items-center gap-2">
                                        <input type="text" wire:model.live.debounce.300ms="pollChoices.{{ $index }}" maxlength="100"
                                            placeholder="Pilihan {{ $index + 1 }}"
                                            class="min-h-[44px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[14px] outline-none focus:border-purple-500">
                                        <button type="button" wire:click="removePollChoice({{ $index }})"
                                            title="Hapus pilihan"
                                            @disabled(count($pollChoices) <= 2)
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-red-200 text-red-700 disabled:opacity-30">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @error('pollChoices') <p class="mt-1 text-[13px] text-red-600">{{ $message }}</p> @enderror
                            @error('pollChoices.*') <p class="mt-1 text-[13px] text-red-600">{{ $message }}</p> @enderror

                            <button type="button" wire:click="addPollChoice" @disabled(count($pollChoices) >= $maxPollChoices)
                                class="mt-2 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-dashed border-[#E2E8F0] font-semibold text-[#64748B] disabled:opacity-40">
                                <i data-lucide="plus" class="h-4 w-4"></i> Tambah Pilihan
                            </button>
                    </div>
                    @else
                        <button type="button" wire:click="$set('isPoll', true)"
                            class="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-slate-100 text-[14px] font-semibold text-[#334155]">
                            <i data-lucide="bar-chart-3" class="h-4 w-4"></i> Jadikan Polling
                        </button>
                    @endif

                    <div class="grid grid-cols-2 gap-2 pb-safe">
                        <button type="button" wire:click="closeForm"
                            class="flex min-h-[48px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Bagikan</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
