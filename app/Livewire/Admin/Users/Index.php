<?php

namespace App\Livewire\Admin\Users;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role_id = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-user'), 403);
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'role_id']);
        $this->resetValidation();
    }

    /**
     * Hanya pemegang permission "manage-role" yang boleh memberikan role Super Admin.
     */
    protected function canAssignRole(Role $role): bool
    {
        if ($role->slug !== 'super_admin') {
            return true;
        }

        return auth()->user()->hasPermission('manage-role');
    }

    /**
     * Cek apakah user adalah Super Admin aktif terakhir di sistem.
     */
    protected function isLastActiveSuperAdmin(User $user): bool
    {
        if ($user->role?->slug !== 'super_admin') {
            return false;
        }

        return User::whereRelation('role', 'slug', 'super_admin')
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->doesntExist();
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->role_id = $user->role_id ? (string) $user->role_id : '';
        $this->showForm = true;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $isUpdate = $this->editingId !== null;
        $user = $isUpdate ? User::findOrFail($this->editingId) : new User;

        $this->authorize($isUpdate ? 'update' : 'create', $user);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.($user->id ?? 'NULL').',id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai user lain.',
            'role_id.required' => 'Role wajib dipilih.',
            'role_id.exists' => 'Role tidak valid.',
        ]);

        if (! $isUpdate) {
            $data['password'] = Hash::make('password123');
            $data['status'] = 'active';
        }

        $role = Role::findOrFail($data['role_id']);

        if (! $this->canAssignRole($role)) {
            session()->flash('error', 'Hanya pengelola Role & Akses yang dapat memberikan role Super Admin.');

            return;
        }

        if ($isUpdate && $user->role?->slug !== $role->slug && $this->isLastActiveSuperAdmin($user)) {
            session()->flash('error', 'Super Admin aktif terakhir tidak boleh diubah rolenya.');

            return;
        }

        $data['phone'] = $data['phone'] ?: null;

        $user->fill($data)->save();

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => $isUpdate ? 'update' : 'create',
            'module' => 'users',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'description' => ($isUpdate ? 'Memperbarui user ' : 'Membuat user ').$user->email,
            'new_values' => $user->fresh()->toArray(),
        ]);

        $this->closeForm();
        session()->flash('success', $isUpdate
            ? 'User '.$user->email.' berhasil diperbarui.'
            : 'User '.$user->email.' berhasil dibuat (password awal: password123).');
    }

    public function changeRole(int $id, int $roleId): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $role = Role::findOrFail($roleId);

        if ($user->id === auth()->id() && $user->role_id !== $role->id) {
            session()->flash('error', 'Tidak bisa mengubah role akun sendiri.');

            return;
        }

        if (! $this->canAssignRole($role)) {
            session()->flash('error', 'Hanya pengelola Role & Akses yang dapat memberikan role Super Admin.');

            return;
        }

        if ($user->role?->slug === 'super_admin' && ! auth()->user()->hasPermission('manage-role')) {
            session()->flash('error', 'Role Super Admin hanya bisa diubah oleh pengelola Role & Akses.');

            return;
        }

        if ($user->role_id !== $role->id && $this->isLastActiveSuperAdmin($user)) {
            session()->flash('error', 'Super Admin aktif terakhir tidak boleh diubah rolenya.');

            return;
        }

        $oldRole = $user->role?->name ?? '-';
        $user->update(['role_id' => $role->id]);

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'update', 'module' => 'users',
            'subject_type' => User::class, 'subject_id' => $user->id,
            'description' => 'Mengubah role user '.$user->email.' dari '.$oldRole.' menjadi '.$role->name,
        ]);

        session()->flash('success', 'Role '.$user->email.' diubah menjadi '.$role->name.'.');
    }

    public function toggleStatus(int $id): void
    {
        $this->authorize('update', User::class);
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Tidak bisa menonaktifkan akun sendiri.');

            return;
        }

        if ($user->status === 'active' && $this->isLastActiveSuperAdmin($user)) {
            session()->flash('error', 'Super Admin aktif terakhir tidak boleh dinonaktifkan.');

            return;
        }

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'update', 'module' => 'users',
            'subject_type' => User::class, 'subject_id' => $user->id,
            'description' => 'Mengubah status user '.$user->email.' menjadi '.$user->status,
        ]);
    }

    public function resetPassword(int $id): void
    {
        $this->authorize('update', User::class);
        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make('password123')]);
        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'update', 'module' => 'users',
            'subject_type' => User::class, 'subject_id' => $user->id,
            'description' => 'Mereset password user '.$user->email,
        ]);
        session()->flash('success', 'Password '.$user->email.' direset ke password123.');
    }

    #[Layout('layouts.admin', ['title' => 'User'])]
    public function render()
    {
        return view('livewire.admin.users.index', [
            'users' => User::with(['role', 'resident'])
                ->when($this->search, fn ($q) => $q->where(function ($qq) {
                    $qq->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%");
                }))
                ->when($this->roleFilter, fn ($q) => $q->where('role_id', $this->roleFilter))
                ->latest()->paginate(10),
            'roles' => Role::query()
                // Role Super Admin hanya boleh diberikan oleh pengelola Role & Akses.
                ->when(
                    ! auth()->user()->hasPermission('manage-role'),
                    fn ($query) => $query->where('slug', '!=', 'super_admin')
                )
                ->orderBy('name')->get(),
            'canAssignSuperAdmin' => auth()->user()->hasPermission('manage-role'),
        ]);
    }
}
