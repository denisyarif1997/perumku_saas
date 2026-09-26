<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari role..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
        <button type="button" wire:click="openCreate"
            class="flex min-h-[48px] items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white">
            <i data-lucide="plus" class="h-5 w-5"></i><span class="hidden sm:inline">Tambah</span>
        </button>
    </div>

    <div class="space-y-2">
        @forelse ($roles as $role)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[16px] font-bold">{{ $role->name }}</p>
                        <p class="mt-0.5 font-mono text-[12px] text-[#64748B]">{{ $role->slug }}</p>
                        @if ($role->description)
                            <p class="mt-1 line-clamp-2 text-[13px] text-[#64748B]">{{ $role->description }}</p>
                        @endif
                        <p class="mt-2 text-[13px] text-[#64748B]">{{ $role->users_count }} user · {{ $role->permissions_count }} permission</p>
                    </div>
                    @if ($role->status === 'active')
                        <x-ui.badge color="green">Aktif</x-ui.badge>
                    @else
                        <x-ui.badge color="slate">Nonaktif</x-ui.badge>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <button type="button" wire:click="openManage({{ $role->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-sky-200 text-[14px] font-semibold text-sky-700">
                        <i data-lucide="key-round" class="h-4 w-4"></i> Akses
                    </button>
                    <button type="button" wire:click="openEdit({{ $role->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold">
                        <i data-lucide="pencil" class="h-4 w-4"></i> Ubah
                    </button>
                    <button type="button" wire:click="delete({{ $role->id }})" wire:confirm="Hapus role {{ $role->name }}?" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700">
                        <i data-lucide="trash-2" class="h-4 w-4"></i> Hapus
                    </button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="shield-check" title="Belum ada role" subtitle="Tambahkan role pertama di atas." />
        @endforelse
    </div>

    <div>{{ $roles->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div wire:click="closeForm" class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                <div class="flex shrink-0 items-center justify-between p-5 pb-3">
                    <p class="text-lg font-bold">{{ $editingId ? 'Ubah Role' : 'Tambah Role' }}</p>
                    <button wire:click="closeForm" class="flex h-10 w-10 items-center justify-center rounded-xl border"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <form wire:submit="save" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <x-ui.field label="Nama Role" :error="$errors->first('name')">
                        <input wire:model="name" placeholder="Contoh: Bendahara"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                    </x-ui.field>
                    <x-ui.field label="Deskripsi (opsional)" :error="$errors->first('description')">
                        <input wire:model="description" placeholder="Keterangan singkat role"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                    </x-ui.field>
                    <div>
                        <p class="mb-2 text-[14px] font-medium">Permission Awal</p>
                        <div class="space-y-3">
                            <p class="rounded-xl bg-slate-50 p-3 text-[13px] text-[#64748B]">
                                Pilih permission awal. Tambahkan <strong>Akses Area Admin</strong> bila role ini perlu masuk area admin.
                            </p>
                            @foreach ($permissions as $group => $items)
                                <div class="rounded-xl border border-[#E2E8F0] p-3">
                                    <p class="mb-2 text-[12px] font-semibold uppercase tracking-wide text-[#64748B]">{{ $groupLabels[$group] ?? ucfirst($group) }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($items as $permission)
                                            <button type="button" wire:click="togglePermission({{ $permission->id }})"
                                                class="rounded-full px-3 py-1.5 text-[13px] font-semibold {{ in_array($permission->id, $selectedPermissions, true) ? 'bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 text-white' : 'border border-[#E2E8F0] bg-white' }}">{{ $permission->name }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pb-safe">
                        <button type="button" wire:click="closeForm" class="flex min-h-[48px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($managingRole)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div wire:click="closeManage" class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                <div class="flex shrink-0 items-center justify-between p-5 pb-3">
                    <div>
                        <p class="text-lg font-bold">Kelola Akses</p>
                        <p class="text-[13px] text-[#64748B]">{{ $managingRole->name }} · {{ $managingRole->users_count }} user</p>
                    </div>
                    <button wire:click="closeManage" class="flex h-10 w-10 items-center justify-center rounded-xl border"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <p class="rounded-xl bg-amber-50 p-3 text-[13px] text-amber-800">
                        Centang halaman yang boleh dibuka role ini. Permission aksi terkait &amp; <strong>Akses Area Admin</strong> diberikan otomatis saat disimpan.
                    </p>

                    @php
                        $menuGroup = $permissions->get('menu') ?? collect();
                    @endphp

                    {{-- AKSES PER HALAMAN (menu) --}}
                    <div class="rounded-xl border border-[#E2E8F0] p-3">
                        <p class="mb-1 text-[12px] font-semibold uppercase tracking-wide text-[#64748B]">Akses Per Halaman</p>
                        <p class="mb-3 text-[12px] text-[#64748B]">Menentukan menu apa saja yang tampil di sidebar role ini.</p>
                        <div class="space-y-2">
                            @foreach ($menuGroup as $permission)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#E2E8F0] px-3 py-2.5 transition hover:bg-slate-50 {{ in_array($permission->id, $managePermissions, true) ? 'border-sky-300 bg-sky-50' : '' }}">
                                    <input
                                        type="checkbox"
                                        class="h-5 w-5 shrink-0 rounded accent-sky-600"
                                        {{ in_array($permission->id, $managePermissions, true) ? 'checked' : '' }}
                                        wire:click="toggleManagePermission({{ $permission->id }})"
                                    >
                                    <span class="text-[14px] font-medium {{ in_array($permission->id, $managePermissions, true) ? 'text-sky-900' : 'text-[#0F172A]' }}">
                                        {{ $permission->name }}
                                    </span>
                                    <i data-lucide="chevron-right" class="ml-auto h-4 w-4 shrink-0 text-[#94A3B8]"></i>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- TOGGLE CEPAT SEMUA MENU --}}
                    <div class="flex gap-2">
                        <button type="button" wire:click="toggleAllMenuPermissions(true)"
                            class="flex-1 rounded-xl border border-[#E2E8F0] px-3 py-2 text-[12px] font-semibold text-[#64748B] transition hover:bg-slate-100">
                            Pilih Semua Menu
                        </button>
                        <button type="button" wire:click="toggleAllMenuPermissions(false)"
                            class="flex-1 rounded-xl border border-[#E2E8F0] px-3 py-2 text-[12px] font-semibold text-[#64748B] transition hover:bg-slate-100">
                            Hapus Semua Menu
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pb-safe">
                        <button type="button" wire:click="closeManage" class="flex min-h-[48px] items-center justify-center rounded-xl border border-[#E2E8F0] bg-white font-semibold">Batal</button>
                        <button type="button" wire:click="saveManage" wire:loading.attr="disabled" class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-sky-600 font-semibold text-white disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveManage">Simpan Akses</span>
                            <span wire:loading wire:target="saveManage">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>