@php
    use App\Support\Currency;
@endphp

<div class="space-y-4">
    {{-- Alert messages --}}
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif
    @if (session('info'))
        <x-ui.alert type="info" icon="info">{{ session('info') }}</x-ui.alert>
    @endif

    {{-- Pencarian --}}
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
        <input wire:model.live.debounce.300ms="search" placeholder="Cari judul / isi pengumuman..."
            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>

    {{-- Form tambah / ubah --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <p class="font-bold">{{ $editingId ? 'Ubah Pengumuman' : 'Tambah Pengumuman' }}</p>
            @if ($editingId)
                <button wire:click="cancel" wire:loading.attr="disabled"
                    class="flex min-h-[40px] items-center justify-center rounded-xl border border-[#E2E8F0] px-3 text-[14px] font-semibold text-[#64748B]">Batal</button>
            @endif
        </div>

        <form wire:submit="save" class="space-y-3">
            <x-ui.field label="Judul" :error="$errors->first('title')">
                <input wire:model="title" placeholder="Judul pengumuman"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Isi Pengumuman" :error="$errors->first('body')">
                <textarea wire:model="body" rows="4" placeholder="Isi pengumuman..."
                    class="w-full rounded-xl border border-[#E2E8F0] px-3 py-2.5 text-[15px] outline-none focus:border-[#0F172A]"></textarea>
            </x-ui.field>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Kategori" :error="$errors->first('category')">
                    <select wire:model="category"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                        <option value="general">Umum</option>
                        <option value="maintenance">Pemeliharaan</option>
                        <option value="event">Acara</option>
                        <option value="security">Keamanan</option>
                        <option value="billing">Tagihan</option>
                        <option value="urgent">Darurat</option>
                    </select>
                </x-ui.field>

                <x-ui.field label="Prioritas" :error="$errors->first('priority')">
                    <select wire:model="priority"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                        <option value="low">Rendah</option>
                        <option value="normal">Normal</option>
                        <option value="high">Tinggi</option>
                    </select>
                </x-ui.field>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Tanggal Terbit" :error="$errors->first('published_at')">
                    <input wire:model="published_at" type="datetime-local"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                </x-ui.field>

                <x-ui.field label="Tanggal Berakhir (opsional)" :error="$errors->first('expired_at')">
                    <input wire:model="expired_at" type="datetime-local"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                </x-ui.field>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Status" :error="$errors->first('status')">
                    <select wire:model="status"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Arsip</option>
                    </select>
                </x-ui.field>

                <div class="flex items-end">
                    <label class="flex items-center gap-3 text-[14px]">
                        <input wire:model="is_pinned" type="checkbox" class="h-5 w-5 rounded border-[#E2E8F0]">
                        <span class="font-semibold">Disematkan</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-5 font-semibold text-white disabled:opacity-60">
                    <i data-lucide="save" class="h-5 w-5"></i>
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Simpan' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </form>
    </div>

    
    {{-- Mobile list --}}
    <div class="space-y-2 md:hidden">
        @forelse ($items as $item)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-mono text-[12px] text-[#64748B]">#{{ $item->id }}</p>
                        <p class="mt-0.5 text-[16px] font-bold">{{ Str::limit($item->title, 50) }}</p>
                        <p class="mt-1 line-clamp-2 text-[13px] text-[#64748B]">{{ Str::limit($item->content, 100) }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[12px]">
                            <x-ui.badge color="{{ $item->priorityColor() }}">{{ $item->priorityLabel() }}</x-ui.badge>
                            <x-ui.badge color="{{ $item->status === 'published' ? 'green' : ($item->status === 'draft' ? 'amber' : 'slate') }}">{{ $item->status }}</x-ui.badge>
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button wire:click="edit({{ $item->id }})"
                        class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold">
                        <i data-lucide="pencil" class="h-4 w-4"></i> Ubah
                    </button>
                    @if ($item->status === 'draft')
                        <button wire:click="publish({{ $item->id }})" wire:confirm="Terbitkan pengumuman ini?"
                            class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-emerald-200 text-[14px] font-semibold text-emerald-700">
                            <i data-lucide="send" class="h-4 w-4"></i> Terbitkan
                        </button>
                    @else
                        <button wire:click="archive({{ $item->id }})" wire:confirm="Arsipkan pengumuman ini?"
                            class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-200 text-[14px] font-semibold text-slate-700">
                            <i data-lucide="archive" class="h-4 w-4"></i> Arsip
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="megaphone" title="Belum ada pengumuman" subtitle="Tambahkan pengumuman pertama di atas." />
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Prioritas</th>
                    <th class="px-4 py-3">Terbit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-mono text-[13px] text-[#64748B]">{{ $item->id }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $item->title }}</td>
                        <td class="px-4 py-3">{{ $item->categoryLabel() }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $item->priorityColor() }}">{{ $item->priorityLabel() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $item->published_at?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge color="{{ $item->status === 'published' ? 'green' : ($item->status === 'draft' ? 'amber' : 'slate') }}">{{ $item->status }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="edit({{ $item->id }})" class="font-semibold">Ubah</button>
                            @if ($item->status === 'draft')
                                <button wire:click="publish({{ $item->id }})" wire:confirm="Terbitkan?" class="ml-2 font-semibold text-emerald-700">Terbitkan</button>
                            @endif
                            <button wire:click="delete({{ $item->id }})" wire:confirm="Hapus pengumuman ini?" class="ml-2 font-semibold text-red-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-[#64748B]">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div>{{ $items->links() }}</div>
</div>
