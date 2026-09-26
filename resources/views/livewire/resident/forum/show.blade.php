<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <a href="{{ route('resident.forum.index') }}" wire:navigate class="inline-flex items-center gap-2 text-[14px] font-semibold text-[#64748B]">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali ke forum
    </a>

    {{-- Isi postingan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4 {{ $post->is_pinned ? 'border-sky-300 ring-1 ring-sky-100' : '' }}">
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($post->is_pinned)
                <x-ui.badge color="sky"><i data-lucide="pin" class="h-3 w-3"></i> Disematkan</x-ui.badge>
            @endif
            <x-ui.badge color="{{ $post->categoryColor() }}">{{ $post->categoryLabel() }}</x-ui.badge>
        </div>
        <p class="mt-2 text-[18px] font-bold leading-snug">{{ $post->title }}</p>
        <p class="mt-2 whitespace-pre-line text-[14px] text-[#334155]">{{ $post->body }}</p>
        <div class="mt-3 flex items-center justify-between gap-2 border-t border-[#E2E8F0] pt-3">
            <div class="flex min-w-0 items-center gap-2">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[13px] font-bold">{{ strtoupper(substr($post->authorName(), 0, 1)) }}</div>
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold">{{ $post->authorName() }}@if ($house = $post->authorHouseLabel()) <span class="font-normal text-[#64748B]">· {{ $house }}</span>@endif</p>
                    <p class="text-[12px] text-[#64748B]">{{ $post->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            @if (auth()->user()->can('delete', $post))
                <button type="button" wire:click="deletePost" wire:confirm="Hapus postingan ini beserta semua komentarnya?"
                    class="flex min-h-[36px] items-center gap-1 rounded-xl border border-red-200 px-3 text-[13px] font-semibold text-red-700">
                    <i data-lucide="trash-2" class="h-4 w-4"></i> Hapus
                </button>
            @endif
        </div>
    </div>
    {{-- Polling --}}
    @if ($post->is_poll)
        <div class="space-y-3 rounded-2xl border border-[#E2E8F0] bg-white p-4" wire:target="vote,editVote,closePoll">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <x-ui.badge color="purple"><i data-lucide="bar-chart-3" class="h-3 w-3"></i> Polling</x-ui.badge>
                    <x-ui.badge color="slate">{{ $post->pollTypeLabel() }}</x-ui.badge>
                    @unless ($post->isPollOpen())
                        <x-ui.badge color="red"><i data-lucide="lock" class="h-3 w-3"></i> Ditutup</x-ui.badge>
                    @endunless
                </div>
                @if ($canManagePoll)
                    <button type="button" wire:click="closePoll"
                        class="flex min-h-[34px] items-center gap-1 rounded-xl border border-[#E2E8F0] px-3 text-[13px] font-semibold text-[#334155]">
                        <i data-lucide="{{ $post->isPollOpen() ? 'lock' : 'unlock' }}" class="h-3.5 w-3.5"></i>
                        {{ $post->isPollOpen() ? 'Tutup Polling' : 'Buka Lagi' }}
                    </button>
                @endif
            </div>

            @if ($post->poll_closes_at)
                <p class="flex items-center gap-1.5 text-[12px] text-[#64748B]">
                    <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                    {{ $post->isPollOpen() ? 'Ditutup pada' : 'Berakhir pada' }} {{ $post->poll_closes_at->format('d/m/Y H:i') }}
                </p>
            @endif

            {{-- Form memilih, tampil saat polling terbuka dan warga belum memilih --}}
            @if ($canVote && (empty($mySelections) || $editingVote))
                <form wire:submit="vote" class="space-y-2.5">
                    <div class="space-y-2">
                        @foreach ($post->pollOptions() as $optionKey => $optionLabel)
                            @php($isPicked = in_array($optionKey, $selectedOptions, true))
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 transition {{ $isPicked ? 'border-teal-600 bg-teal-50' : 'border-[#E2E8F0] active:bg-slate-50' }}">
                                <input type="{{ $post->poll_type === 'multiple' ? 'checkbox' : 'radio' }}"
                                    name="pollOption{{ $post->poll_type === 'multiple' ? '[]' : '' }}"
                                    value="{{ $optionKey }}" @checked($isPicked)
                                    wire:click="selectOption('{{ $optionKey }}')"
                                    wire:loading.attr="disabled" wire:target="selectOption"
                                    class="h-4 w-4 accent-teal-700">
                                <span class="min-w-0 flex-1 text-[14px]">{{ $optionLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedOptions') <p class="text-[13px] text-red-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-2">
                        @if (! empty($mySelections))
                            <button type="button" wire:click="cancelVoteEdit"
                                class="flex min-h-[44px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">
                                Batal
                            </button>
                        @else
                            <span></span>
                        @endif
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                            <i data-lucide="check" class="h-4 w-4"></i>
                            <span wire:loading.remove wire:target="vote">Kirim Pilihan</span>
                            <span wire:loading wire:target="vote">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            @endif

            {{-- Hasil polling: hanya untuk yang sudah memilih, pembuat polling, dan pengelola --}}
            @if ($canViewResults)
                <div class="space-y-2.5 border-t border-[#E2E8F0] pt-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="flex items-center gap-1.5 text-[13px] font-semibold">
                            <i data-lucide="users" class="h-4 w-4"></i>
                            {{ $pollVotersCount }} warga sudah memilih
                        </p>
                        @if ($canVote && ! $editingVote)
                            <button type="button" wire:click="editVote"
                                class="flex min-h-[34px] shrink-0 items-center rounded-xl border border-[#E2E8F0] px-3 text-[13px] font-semibold text-[#334155]">
                                Ganti Pilihan
                            </button>
                        @endif
                    </div>

                    @foreach ($pollResults as $result)
                        <div>
                            <div class="flex items-center justify-between gap-2 text-[13px]">
                                <span class="min-w-0 flex-1 font-medium">
                                    {{ $result['label'] }}
                                    @if (in_array($result['key'], $mySelections, true))
                                        <i data-lucide="check" class="inline h-3.5 w-3.5 text-teal-700"></i>
                                    @endif
                                </span>
                                <span class="shrink-0 text-[#64748B]">{{ $result['percent'] }}% · {{ $result['votes'] }} suara</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-teal-700" style="width: {{ $result['percent'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="flex items-start gap-1.5 border-t border-[#E2E8F0] pt-3 text-[13px] text-[#64748B]">
                    <i data-lucide="eye-off" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                    @if ($post->isPollOpen())
                        Hasil polling tampil setelah Anda memilih.
                    @else
                        Polling sudah ditutup. Hasil hanya dilihat warga yang sudah memilih.
                    @endif
                </p>
            @endif
        </div>
    @endif


    {{-- Komentar --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="flex items-center justify-between gap-2">
            <p class="font-bold">Komentar ({{ $commentCount }})</p>
            @if ($commentCount > 0)
                <span class="inline-flex items-center gap-1 text-[12px] text-[#64748B]">
                    <i data-lucide="message-circle" class="h-3.5 w-3.5"></i> Terbaru di bawah
                </span>
            @endif
        </div>

        <div class="mt-3 space-y-3" wire:loading.class="opacity-60" wire:target="addComment,deleteComment">
            @forelse ($post->comments as $comment)
                <div class="rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[12px] font-bold text-white">{{ strtoupper(substr($comment->authorName(), 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="truncate text-[14px] font-bold">{{ $comment->authorName() }}</p>
                                <p class="text-[12px] text-[#64748B]">{{ $comment->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        @if (auth()->user()->can('delete', $comment))
                            <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Hapus komentar ini?"
                                title="Hapus komentar"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-red-700 ring-1 ring-red-200">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        @endif
                    </div>
                    <p class="mt-1.5 whitespace-pre-line text-[14px] leading-relaxed text-slate-700">{{ $comment->body }}</p>
                </div>
            @empty
                <x-ui.empty-state icon="message-circle" title="Belum ada komentar" subtitle="Jadilah yang pertama berkomentar." />
            @endforelse
        </div>

        <form wire:submit="addComment" class="mt-4">
            <div class="flex items-end gap-2">
                <span class="mb-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-700 text-[13px] font-bold text-white">
                    {{ strtoupper(substr(auth()->user()->name ?? 'W', 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <x-ui.field label="Tulis Komentar" :error="$errors->first('body')">
                        <textarea wire:model.live.debounce.200ms="body" rows="2" maxlength="1000" placeholder="Tulis komentar yang sopan..."
                            class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-teal-600"></textarea>
                        <p class="mt-1 text-right text-[12px] text-[#64748B]">{{ mb_strlen($body) }}/1000</p>
                    </x-ui.field>
                </div>
            </div>
            <button type="submit" wire:loading.attr="disabled"
                class="mt-2 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-teal-700 font-semibold text-white disabled:opacity-60">
                <i data-lucide="send" class="h-4 w-4"></i>
                <span wire:loading.remove wire:target="addComment">Kirim</span>
                <span wire:loading wire:target="addComment">Mengirim...</span>
            </button>
        </form>
    </div>
</div>
