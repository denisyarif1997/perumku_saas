<?php

namespace Tests\Unit;

use App\Livewire\Admin\Roles\Index as RoleIndex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_sees_all_menu_items(): void
    {
        $user = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->firstOrFail();

        $sections = AdminMenu::forUser($user);
        $allItems = collect(AdminMenu::sections())->flatMap(fn ($s) => $s['items']);

        $this->assertSame($allItems->count(), collect($sections)->sum(fn ($s) => count($s['items'])));
    }

    public function test_finance_role_only_sees_its_menus(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('slug', 'finance')->firstOrFail()->id]);

        $routes = collect(AdminMenu::forUser($user))
            ->flatMap(fn ($s) => $s['items'])
            ->pluck(0)
            ->all();

        $this->assertContains('admin.dashboard', $routes);
        $this->assertContains('admin.ipl.billings.index', $routes);
        $this->assertContains('admin.cash.transactions.index', $routes);
        // manage-billing juga membuka menu Air.
        $this->assertContains('admin.water.readings', $routes);

        // Menu di luar permission finance tidak tampil.
        $this->assertNotContains('admin.estates.index', $routes);
        $this->assertNotContains('admin.info.announcements', $routes);
        $this->assertNotContains('admin.roles.index', $routes);
    }

    public function test_sections_without_visible_items_are_hidden(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('slug', 'security')->firstOrFail()->id]);

        $labels = collect(AdminMenu::forUser($user))->pluck('label')->all();

        $this->assertNotContains('Data Master', $labels);
        $this->assertNotContains('Sistem', $labels);
    }

    public function test_guest_or_user_without_role_sees_nothing(): void
    {
        $this->assertSame([], AdminMenu::forUser(null));
        $this->assertSame([], AdminMenu::forUser(User::factory()->create()));
    }

    public function test_inventory_menu_permissions_exist_in_database(): void
    {
        $slugs = Permission::where('group', 'menu')->pluck('slug');

        $this->assertTrue($slugs->contains('menu-inventory-items'));
        $this->assertTrue($slugs->contains('menu-inventory-loans'));
        $this->assertTrue(Permission::where('slug', 'manage-inventory')->exists());
    }

    public function test_role_access_page_lists_the_inventory_menu_permissions(): void
    {
        $superAdmin = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->firstOrFail();
        $role = Role::where('slug', 'maintenance')->firstOrFail();

        // Permission menu inventaris harus muncul di daftar "Akses Per Halaman".
        Livewire::actingAs($superAdmin)
            ->test(RoleIndex::class)
            ->call('openManage', $role->id)
            ->assertSee('Menu: Daftar Barang')
            ->assertSee('Menu: Pinjam Barang');
    }

    public function test_inventory_menu_grants_access_to_the_sidebar(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('slug', 'maintenance')->firstOrFail()->id,
        ]);

        $routes = collect(AdminMenu::forUser($user))
            ->flatMap(fn ($s) => $s['items'])
            ->pluck(0)
            ->all();

        $this->assertContains('admin.inventory.items.index', $routes);
        $this->assertContains('admin.inventory.loans.index', $routes);
    }

    public function test_sync_permissions_command_registers_inventory_permissions(): void
    {
        // Simulasikan kondisi setelah deploy: permission modul baru belum
        // pernah di-seed, sehingga halaman Role & Akses tidak menampilkannya.
        Permission::whereIn('slug', [
            'manage-inventory',
            'menu-inventory-items',
            'menu-inventory-loans',
        ])->delete();

        $this->assertFalse(Permission::where('slug', 'manage-inventory')->exists());

        $this->artisan('app:sync-permissions')->assertSuccessful();

        // Setelah perintah dijalankan, permission harus terdaftar lagi.
        $this->assertTrue(Permission::where('slug', 'manage-inventory')->exists());
        $this->assertTrue(Permission::where('slug', 'menu-inventory-items')->exists());
        $this->assertTrue(Permission::where('slug', 'menu-inventory-loans')->exists());
    }

    public function test_sync_permissions_command_is_idempotent_and_keeps_existing_users(): void
    {
        $userCountBefore = User::count();

        $this->artisan('app:sync-permissions')->assertSuccessful();
        $this->artisan('app:sync-permissions')->assertSuccessful();

        $this->assertSame($userCountBefore, User::count());
        $this->assertSame(1, Permission::where('slug', 'manage-inventory')->count());
    }
}
