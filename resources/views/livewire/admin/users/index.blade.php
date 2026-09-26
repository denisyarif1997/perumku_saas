<div class="space-y-4">
    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nama / email..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 outline-none focus:border-[#0F172A]">
        </div>
        <button type="button" wire:click="openCreate"
            class="flex min-h-[48px] items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white">
            <i data-lucide="plus" class="h-5 w-5"></i><span class="hidden sm:inline">Tambah</span>
        </button>
    </div>

    <select wire:model.live="roleFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px] md:max-w-xs">
        <option value="">Semua Role</option>
        @foreach ($roles as $role)
            <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
    </select>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    @unless ($canAssignSuperAdmin)
        <div class="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-900">
            <i data-lucide="lock" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <p>Role <span class="font-semibold">Super Admin</span> terkunci untuk akun Anda. Hanya pengelola
                <span class="font-semibold">Role &amp; Akses</span> yang dapat memberikan atau mengubah role ini.</p>
        </div>
    @endunless

    <div class="space-y-2">
        @forelse ($users as $user)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $user->name }}</p>
                        <p class="truncate text-[13px] text-[#64748B]">{{ $user->email }} • {{ $user->role?->name ?? '-' }}</p>
                    </div>
                    <x-ui.badge color="{{ $user->status === 'active' ? 'green' : 'red' }}">{{ $user->status === 'active' ? '● Aktif' : '○ Nonaktif' }}</x-ui.badge>
                </div>
                <div class="mt-3">
                    <x-ui.field label="Role">
                        <select wire:change="changeRole({{ $user->id }}, $event.target.value)" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            @php $roleLocked = $user->role && ! $roles->contains('id', $user->role_id); @endphp
                            @if ($roleLocked)
                                {{-- Role saat ini tidak boleh diubah oleh pengguna tanpa hak manage-role --}}
                                <option value="{{ $user->role_id }}" selected disabled>{{ $user->role->name }} (terkunci)</option>
                            @endif
                            @foreach ($roles as $role)
                                @continue (! $canAssignSuperAdmin && $role->slug === 'super_admin')
                                <option value="{{ $role->id }}" @selected((int) $user->role_id === (int) $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <button wire:click="openEdit({{ $user->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border text-[14px] font-semibold"><i data-lucide="pencil" class="h-4 w-4"></i> Ubah</button>
                    <button wire:click="toggleStatus({{ $user->id }})" class="flex min-h-[44px] items-center justify-center rounded-xl border text-[14px] font-semibold">Ubah Status</button>
                    <button wire:click="resetPassword({{ $user->id }})" wire:confirm="Reset password user ini?" class="flex min-h-[44px] items-center justify-center rounded-xl border text-[14px] font-semibold">Reset Password</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="users" title="Belum ada user" subtitle="Tambahkan user pertama melalui tombol Tambah di atas." />
        @endforelse
    </div>
    <div>{{ $users->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div wire:click="closeForm" class="absolute inset-0 bg-black/40"></div>
            <div class="relative flex max-h-[92dvh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl">
                <div class="flex shrink-0 items-center justify-between p-5 pb-3">
                    <p class="text-lg font-bold">{{ $editingId ? 'Ubah User' : 'Tambah User' }}</p>
                    <button wire:click="closeForm" class="flex h-10 w-10 items-center justify-center rounded-xl border"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <form wire:submit="save" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-8">
                    <x-ui.field label="Nama" :error="$errors->first('name')">
                        <input wire:model="name" placeholder="Nama lengkap"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                    </x-ui.field>
                    <x-ui.field label="Email" :error="$errors->first('email')">
                        <input wire:model="email" type="email" placeholder="nama@email.com"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                    </x-ui.field>
                    <x-ui.field label="Telepon (opsional)" :error="$errors->first('phone')">
                        <input wire:model="phone" placeholder="08xxxxxxxxxx"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                    </x-ui.field>
                    <x-ui.field label="Role" :error="$errors->first('role_id')">
                        <select wire:model="role_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            <option value="">Pilih role</option>
                            @foreach ($roles as $role)
                                @continue (! $canAssignSuperAdmin && $role->slug === 'super_admin')
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    @if (! $editingId)
                        <p class="text-[13px] text-[#64748B]">Password awal user baru: <span class="font-mono font-semibold text-[#0F172A]">password123</span></p>
                    @endif
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
</div>
