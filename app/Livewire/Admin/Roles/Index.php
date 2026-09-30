<?php

namespace App\Livewire\Admin\Roles;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /** Label grup permission yang ditampilkan di UI. */
    public const GROUP_LABELS = [
        'general' => 'Umum',
        'master' => 'Data Master',
        'billing' => 'Keuangan & IPL',
        'communication' => 'Komunikasi',
        'service' => 'Layanan Warga',
        'marketplace' => 'Marketplace',
        'security' => 'Keamanan',
        'finance' => 'Keuangan',
        'inventory' => 'Inventaris',
        'system' => 'Sistem',
        'menu' => 'Akses Menu (per halaman)',
    ];

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    /** @var array<int> */
    public array $selectedPermissions = [];

    public ?int $managingId = null;

    /** @var array<int> */
    public array $managePermissions = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-role'), 403);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Role::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->authorize('update', $role);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->description = $role->description ?? '';
        $this->selectedPermissions = $this->ids($role->permissions);
        $this->showForm = true;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'selectedPermissions']);
        $this->resetValidation();
    }

    protected function ids($permissions): array
    {
        return $permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function save(): void
    {
        $isUpdate = $this->editingId !== null;
        $role = $isUpdate ? Role::findOrFail($this->editingId) : new Role;

        $this->authorize($isUpdate ? 'update' : 'create', $role);

        if ($role->exists && $role->slug === 'super_admin') {
            session()->flash('error', 'Role Super Admin tidak boleh diubah.');

            return;
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['integer', 'exists:permissions,id'],
        ], [
            'name.required' => 'Nama role wajib diisi.',
        ]);

        $slug = Str::slug($this->name, '_');

        if (Role::where('slug', $slug)->where('id', '!=', $role->id ?? 0)->exists()) {
            $this->addError('name', 'Nama role sudah dipakai, gunakan nama lain.');

            return;
        }

        $role->fill([
            'name' => $data['name'],
            'slug' => $role->exists ? $role->slug : $slug,
            'description' => $data['description'] ?: null,
            'status' => 'active',
        ])->save();

        $role->permissions()->sync($data['selectedPermissions'] ?? []);

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => $isUpdate ? 'update' : 'create',
            'module' => 'roles',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'description' => ($isUpdate ? 'Memperbarui role ' : 'Membuat role ').$role->name,
            'new_values' => $role->fresh()->toArray(),
        ]);

        $this->closeForm();
        session()->flash('success', 'Role '.$role->name.' berhasil disimpan.');
    }

    public function togglePermission(int $permissionId): void
    {
        if (in_array($permissionId, $this->selectedPermissions, true)) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, [$permissionId]));
        } else {
            $this->selectedPermissions[] = $permissionId;
        }
    }

    public function delete(int $id): void
    {
        $role = Role::withCount('users')->findOrFail($id);
        $this->authorize('delete', $role);

        if (in_array($role->slug, ['super_admin', 'resident'], true)) {
            session()->flash('error', 'Role sistem tidak boleh dihapus.');

            return;
        }

        if ($role->users_count > 0) {
            session()->flash('error', 'Role '.$role->name.' masih dipakai '.$role->users_count.' user.');

            return;
        }

        $name = $role->name;
        $old = $role->toArray();
        $role->permissions()->detach();
        $role->delete();

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'module' => 'roles',
            'subject_type' => Role::class,
            'subject_id' => $id,
            'description' => 'Menghapus role '.$name,
            'old_values' => $old,
        ]);

        session()->flash('success', 'Role '.$name.' berhasil dihapus.');
    }

    public function openManage(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->authorize('update', $role);

        $this->managingId = $role->id;
        $this->managePermissions = $this->ids($role->permissions);
    }

    public function closeManage(): void
    {
        $this->reset(['managingId', 'managePermissions']);
    }

    public function toggleManagePermission(int $permissionId): void
    {
        if (in_array($permissionId, $this->managePermissions, true)) {
            $this->managePermissions = array_values(array_diff($this->managePermissions, [$permissionId]));
        } else {
            $this->managePermissions[] = $permissionId;
        }
    }

    /**
     * Centang/hapus semua permission menu sekaligus.
     */
    public function toggleAllMenuPermissions(bool $select): void
    {
        $menuIds = Permission::where('group', 'menu')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($select) {
            $this->managePermissions = array_values(array_unique(array_merge($this->managePermissions, $menuIds)));

            return;
        }

        $this->managePermissions = array_values(array_diff($this->managePermissions, $menuIds));
    }

    public function saveManage(): void
    {
        $role = Role::findOrFail($this->managingId ?? 0);
        $this->authorize('update', $role);

        if ($role->slug === 'super_admin') {
            session()->flash('error', 'Role Super Admin tidak boleh diubah.');

            return;
        }

        $validIds = Permission::pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_values(array_intersect($this->managePermissions, $validIds));

        // Auto-sync: pastikan permission aksi yang diperlukan menu terpilih
        // ikut diberikan, agar aksi di dalam halaman tidak ditolak 403.
        $menuPerms = Permission::where('group', 'menu')->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id);
        $actionIds = [];
        foreach (AdminMenu::sections() as $section) {
            foreach ($section['items'] as $item) {
                [$route, $icon, $label, $pattern, $menuSlugs, $actionSlugs] = $item;
                foreach ($menuSlugs as $menuSlug) {
                    $menuId = $menuPerms[$menuSlug] ?? null;
                    if ($menuId !== null && in_array($menuId, $selected, true)) {
                        foreach ($actionSlugs as $slug) {
                            $actionId = Permission::where('slug', $slug)->value('id');
                            if ($actionId) {
                                $actionIds[] = (int) $actionId;
                            }
                        }
                    }
                }
            }
        }
        // Akses Area Admin wajib bila ada menu admin yang dicentang.
        if ($selected !== []) {
            $accessAdminId = Permission::where('slug', 'access-admin')->value('id');
            if ($accessAdminId) {
                $actionIds[] = (int) $accessAdminId;
            }
        }

        $syncIds = array_values(array_unique(array_merge($selected, $actionIds)));

        $role->permissions()->sync($syncIds);

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'roles',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'description' => 'Memperbarui permission role '.$role->name,
            'new_values' => ['permission_ids' => $syncIds],
        ]);

        $this->closeManage();
        session()->flash('success', 'Permission role '.$role->name.' berhasil disimpan.');
    }

    /**
     * @return Collection<int, Permission>
     */
    protected function groupedPermissions(): Collection
    {
        // Grup "menu" ditaruh paling bawah agar daftar aksi tetap di atas.
        return Permission::orderBy('group')->orderBy('name')
            ->get()
            ->sortBy(fn (Permission $p) => $p->group === 'menu' ? 1 : 0)
            ->values();
    }

    #[Layout('layouts.admin', ['title' => 'Role & Permission'])]
    public function render()
    {
        $permissions = $this->groupedPermissions()->groupBy('group');

        return view('livewire.admin.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])
                ->when($this->search, fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(10),
            'permissions' => $permissions,
            'groupLabels' => self::GROUP_LABELS,
            'managingRole' => $this->managingId ? Role::withCount('users')->find($this->managingId) : null,
            'notice' => session('notice'),
        ]);
    }
}
