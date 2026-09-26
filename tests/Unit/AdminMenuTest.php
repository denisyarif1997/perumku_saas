<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
