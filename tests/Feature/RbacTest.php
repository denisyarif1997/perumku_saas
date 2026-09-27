<?php

namespace Tests\Feature;

use App\Livewire\Admin\Houses\Index as HouseIndex;
use App\Livewire\Admin\Ipl\Billings\Index as BillingIndex;
use App\Livewire\Admin\Residents\Index as ResidentIndex;
use App\Livewire\Admin\Roles\Index as RoleIndex;
use App\Livewire\Admin\Users\Index as UserIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Resident\Profile as ResidentProfile;
use App\Models\Billing;
use App\Models\House;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\IplRate;
use App\Models\Permission;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function admin(): User
    {
        // Akun platform tidak terikat estate, jadi diambil tanpa global scope
        // agar tetap ditemukan meski test sedang beractingAs sebagai user estate.
        return $this->findUserUnscoped('admin@housinghub.id');
    }

    /**
     * Naikkan satu akun warga dari seeder ke role tertentu, lalu muat ulang
     * relasinya. Dipakai untuk menyimulasikan warga yang sekaligus bertugas
     * sebagai admin/finance/rt di condominium yang sama.
     */
    protected function giveResidentUserRole(string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::where('email', 'warga.a.1@housinghub.id')->firstOrFail();

        $user->update(['role_id' => $role->id]);

        return $user->fresh()->load('role.permissions');
    }

    public function test_admin_can_open_roles_page(): void
    {
        $this->actingAs($this->admin())->get(route('admin.roles.index'))->assertOk();
    }

    public function test_finance_cannot_open_roles_page(): void
    {
        $financeRole = Role::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['role_id' => $financeRole->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $permission = Permission::where('slug', 'manage-complaint')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->set('name', 'Koordinator Lapangan')
            ->set('description', 'Koordinasi petugas lapangan')
            ->set('selectedPermissions', [$permission->id])
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::where('name', 'Koordinator Lapangan')->firstOrFail();
        $this->assertTrue($role->permissions->contains($permission));
    }

    public function test_admin_cannot_delete_role_in_use(): void
    {
        $role = Role::where('slug', 'finance')->firstOrFail();
        User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('delete', $role->id)
            ->assertSee('masih dipakai');

        $this->assertNotNull($role->fresh());
    }

    public function test_admin_can_delete_unused_role(): void
    {
        $role = Role::create(['name' => 'Role Sementara', 'slug' => 'role_sementara', 'status' => 'active']);

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('delete', $role->id)
            ->assertSee('berhasil dihapus');

        $this->assertNull(Role::find($role->id));
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $role = Role::where('slug', 'rt')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->set('name', 'Ketua RT 01')
            ->set('email', 'rt01@example.com')
            ->set('role_id', (string) $role->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'rt01@example.com')->firstOrFail();
        $this->assertSame($role->id, (int) $user->role_id);
    }

    public function test_admin_can_export_resident_data(): void
    {
        Resident::create([
            'name' => 'Budi Santoso',
            'nik' => '3301010101010001',
            'gender' => 'male',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
            'status' => 'active',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ResidentIndex::class)
            ->set('search', 'Budi')
            ->call('export')
            ->assertFileDownloaded('residents.csv');
    }

    public function test_admin_can_export_house_data(): void
    {
        $estate = HousingEstate::firstOrFail();
        $block = HousingBlock::firstOrFail();

        House::create([
            'housing_estate_id' => $estate->id,
            'housing_block_id' => $block->id,
            'house_number' => '99',
            'address' => 'Jl. Export No. 99',
            'land_area' => 100,
            'building_area' => 80,
            'ownership_status' => 'owner',
            'occupancy_status' => 'occupied',
            'status' => 'active',
        ]);

        Livewire::actingAs($this->admin())
            ->test(HouseIndex::class)
            ->set('search', '99')
            ->call('export')
            ->assertFileDownloaded('houses.csv');
    }

    public function test_admin_can_export_billing_data(): void
    {
        $house = House::firstOrFail();
        $resident = Resident::firstOrFail();
        $rate = IplRate::firstOrFail();

        Billing::create([
            'invoice_number' => 'IPL-2026-001',
            'house_id' => $house->id,
            'resident_id' => $resident->id,
            'ipl_rate_id' => $rate->id,
            'period_month' => 9,
            'period_year' => 2026,
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'paid_amount' => 0,
            'due_date' => now()->toDateString(),
            'status' => 'unpaid',
            'notes' => 'Tagihan export',
            'created_by' => $this->admin()->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(BillingIndex::class)
            ->set('search', 'IPL-2026-001')
            ->call('export')
            ->assertFileDownloaded('billings.csv');
    }

    public function test_user_inherits_role_permissions(): void
    {
        $role = Role::where('slug', 'rt')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->assertTrue($user->hasPermission('manage-residents'));
        $this->assertFalse($user->hasPermission('manage-user'));
    }

    public function test_navbar_hides_menus_without_permission(): void
    {
        $financeRole = Role::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['role_id' => $financeRole->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();

        // Modul keuangan tetap tampil untuk role Finance
        $response->assertSee(route('admin.ipl.billings.index'), false);
        $response->assertSee(route('admin.ipl.payments.index'), false);
        $response->assertSee('Tagihan IPL', false);

        // Modul di luar permission Finance tidak dirender di navigasi
        $response->assertDontSee(route('admin.roles.index'), false);
        $response->assertDontSee(route('admin.users.index'), false);
        $response->assertDontSee(route('admin.houses.index'), false);
        $response->assertDontSee(route('admin.residents.index'), false);
        $response->assertDontSee(route('admin.forum.index'), false);
        $response->assertDontSee('Role &amp; Akses', false);
    }

    public function test_resident_cannot_open_admin_area(): void
    {
        $resident = User::where('email', 'warga.a.1@housinghub.id')->firstOrFail();

        $this->assertTrue($resident->isResident());
        $this->assertFalse($resident->hasPermission('access-admin'));

        $this->actingAs($resident)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($resident)->get(route('admin.ipl.billings.index'))->assertForbidden();
        $this->actingAs($resident)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_resident_profile_offers_admin_button_for_staff_role(): void
    {
        $user = $this->giveResidentUserRole('rt');

        Livewire::actingAs($user)
            ->test(ResidentProfile::class)
            ->assertSee('Beralih ke Tampilan Admin')
            ->assertSee(route('admin.dashboard'), false);
    }

    public function test_resident_profile_hides_admin_button_for_pure_resident(): void
    {
        $user = $this->giveResidentUserRole('resident');

        Livewire::actingAs($user)
            ->test(ResidentProfile::class)
            ->assertDontSee('Beralih ke Tampilan Admin')
            ->assertDontSee(route('admin.dashboard'), false);
    }

    public function test_resident_profile_button_follows_permission_not_role_name(): void
    {
        // Role bernama "finance" tapi akses area admin-nya dicabut lewat
        // halaman Role & Akses: tombol harus ikut hilang, bukan tetap tampil
        // lalu memunculkan 403.
        $financeRole = Role::where('slug', 'finance')->firstOrFail();
        $financeRole->permissions()->detach(
            Permission::where('slug', 'access-admin')->firstOrFail()->id
        );

        Livewire::actingAs($this->giveResidentUserRole('finance'))
            ->test(ResidentProfile::class)
            ->assertDontSee('Beralih ke Tampilan Admin');
    }

    public function test_resident_profile_button_skips_dashboard_without_view_dashboard_permission(): void
    {
        // Akses admin boleh, tetapi tidak boleh melihat dashboard, sehingga
        // tombol harus mengarah ke modul yang benar-benar bisa dimuat.
        $accessAdmin = Permission::where('slug', 'access-admin')->firstOrFail();
        $manageComplaint = Permission::where('slug', 'manage-complaint')->firstOrFail();
        $role = Role::create(['name' => 'Petugas Aduan', 'slug' => 'petugas_aduan', 'status' => 'active']);
        $role->permissions()->sync([$accessAdmin->id, $manageComplaint->id]);

        $user = $this->giveResidentUserRole($role->slug);

        Livewire::actingAs($user)
            ->test(ResidentProfile::class)
            ->assertSee('Beralih ke Tampilan Admin')
            ->assertSee(route('admin.info.complaints'), false)
            ->assertDontSee(route('admin.dashboard'), false);

        // Targetnya benar-benar bisa dimuat, bukan hanya tautan yang terlihat benar.
        $this->actingAs($user)->get(route('admin.info.complaints'))->assertOk();
    }

    public function test_finance_cannot_open_module_outside_permission(): void
    {
        $financeRole = Role::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['role_id' => $financeRole->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('admin.ipl.billings.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.houses.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.residents.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.activity-logs.index'))->assertForbidden();
    }

    public function test_changing_role_permission_affects_its_users(): void
    {
        $role = Role::where('slug', 'finance')->firstOrFail();
        $permission = Permission::where('slug', 'manage-complaint')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->assertFalse($user->hasPermission('manage-complaint'));
        $this->actingAs($user)->get(route('admin.info.complaints'))->assertForbidden();

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('openManage', $role->id)
            ->call('toggleManagePermission', $permission->id)
            ->call('saveManage')
            ->assertHasNoErrors();

        $this->assertTrue($this->withoutEstateScope($user)->hasPermission('manage-complaint'));
        $this->actingAs($this->withoutEstateScope($user))->get(route('admin.info.complaints'))->assertOk();
    }

    public function test_roles_page_shows_permission_groups(): void
    {
        $this->actingAs($this->admin())->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Super Admin', false)
            ->assertSee('Finance', false);

        $role = Role::where('slug', 'finance')->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('openManage', $role->id)
            ->assertSee('Kelola Akses')
            ->assertSee('Akses Area Admin')
            ->assertSee('Akses Per Halaman');
    }

    public function test_role_without_access_admin_cannot_enter_admin_area(): void
    {
        $permission = Permission::where('slug', 'manage-complaint')->firstOrFail();
        $role = Role::create(['name' => 'Petugas Lapangan', 'slug' => 'petugas_lapangan', 'status' => 'active']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->assertTrue($user->hasPermission('manage-complaint'));
        $this->assertFalse($user->hasPermission('access-admin'));

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.info.complaints'))->assertForbidden();
    }

    public function test_role_with_access_admin_can_enter_allowed_module(): void
    {
        $complaint = Permission::where('slug', 'manage-complaint')->firstOrFail();
        $accessAdmin = Permission::where('slug', 'access-admin')->firstOrFail();
        $role = Role::create(['name' => 'Petugas Lapangan 2', 'slug' => 'petugas_lapangan_2', 'status' => 'active']);
        $role->permissions()->sync([$complaint->id, $accessAdmin->id]);

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('admin.info.complaints'))->assertOk();
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_super_admin_sees_full_dashboard(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Total Rumah', false);
        $response->assertSee('Total User', false);
        $response->assertSee('Rumah Terbaru', false);
        $response->assertSee('Pembayaran Terbaru', false);
        $response->assertSee(route('admin.houses.index'), false);
    }

    public function test_finance_dashboard_only_shows_finance_widgets(): void
    {
        $financeRole = Role::where('slug', 'finance')->firstOrFail();
        $user = User::factory()->create(['role_id' => $financeRole->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Pembayaran Terbaru', false);
        $response->assertDontSee('Rumah Terbaru', false);
        $response->assertDontSee('Total User', false);
        $response->assertDontSee('Total Rumah', false);
    }

    public function test_users_page_renders_create_form_modal(): void
    {
        // Buat user paling baru agar tampil di halaman pertama (10 per halaman)
        $target = User::factory()->create(['role_id' => Role::where('slug', 'security')->firstOrFail()->id, 'status' => 'active']);

        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->assertSet('showForm', false)
            // Daftar user terisi tidak boleh menyembunyikan form modal
            ->assertSee('Reset Password')
            ->assertDontSee('Belum ada user')
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSee('Tambah User')
            ->call('closeForm')
            ->assertSet('showForm', false)
            ->call('openEdit', $target->id)
            ->assertSet('showForm', true)
            ->assertSee('Ubah User')
            ->assertSet('role_id', (string) $target->role_id);
    }

    public function test_user_without_manage_role_cannot_assign_super_admin(): void
    {
        // Role khusus: punya manage-user tetapi tidak punya manage-role
        $role = Role::create(['name' => 'Operator User', 'slug' => 'operator_user', 'status' => 'active']);
        $role->permissions()->sync([
            Permission::where('slug', 'view-dashboard')->firstOrFail()->id,
            Permission::where('slug', 'access-admin')->firstOrFail()->id,
            Permission::where('slug', 'manage-user')->firstOrFail()->id,
        ]);

        $actor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();
        $target = User::factory()->create(['role_id' => Role::where('slug', 'security')->firstOrFail()->id, 'status' => 'active']);

        $this->assertTrue($actor->hasPermission('manage-user'));
        $this->assertFalse($actor->hasPermission('manage-role'));

        Livewire::actingAs($actor)
            ->test(UserIndex::class)
            ->assertViewHas('roles', fn ($roles) => ! $roles->contains('slug', 'super_admin'))
            ->assertSee('terkunci')
            ->call('changeRole', $target->id, $superAdminRole->id)
            ->assertSee('Hanya pengelola Role & Akses');

        $this->assertNotSame($superAdminRole->id, $target->fresh()->role_id);
    }

    public function test_last_active_super_admin_cannot_be_demoted_or_disabled(): void
    {
        $admin = $this->admin();
        $admin->update(['role_id' => Role::where('slug', 'super_admin')->firstOrFail()->id]);
        $other = User::factory()->create(['role_id' => Role::where('slug', 'security')->firstOrFail()->id, 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleStatus', $admin->id)
            ->assertSee('akun sendiri');

        // Super admin aktif terakhir tidak boleh diturunkan rolenya oleh admin lain.
        // Akun ini tingkat platform (tanpa estate), karena yang dilindungi adalah
        // akun super_admin global, bukan akun milik sebuah perumahan.
        $admin2 = User::factory()->withoutEstate()->create(['role_id' => Role::where('slug', 'admin')->firstOrFail()->id, 'status' => 'active']);

        Livewire::actingAs($admin2)
            ->test(UserIndex::class)
            ->call('changeRole', $admin->id, $other->role_id)
            ->assertSee('Super Admin aktif terakhir tidak boleh diubah rolenya');

        $this->assertSame('super_admin', $admin->fresh()->role->slug);
    }

    public function test_user_without_manage_role_cannot_change_super_admin_role(): void
    {
        $operatorRole = Role::create(['name' => 'Operator User 2', 'slug' => 'operator_user_2', 'status' => 'active']);
        $operatorRole->permissions()->sync(
            Permission::whereIn('slug', ['access-admin', 'manage-user'])->pluck('id')->all()
        );

        $operator = User::factory()->withoutEstate()->create(['role_id' => $operatorRole->id, 'status' => 'active']);
        $superAdmin = $this->admin();
        $financeRole = Role::where('slug', 'finance')->firstOrFail();

        Livewire::actingAs($operator)
            ->test(UserIndex::class)
            ->call('changeRole', $superAdmin->id, $financeRole->id)
            ->assertSee('Role Super Admin hanya bisa diubah', false);

        $this->assertSame('super_admin', $superAdmin->fresh()->role->slug);
    }

    public function test_super_admin_can_assign_super_admin_to_other_user(): void
    {
        $superAdmin = $this->admin();
        $superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();

        Livewire::actingAs($superAdmin)
            ->test(UserIndex::class)
            ->call('openCreate')
            ->set('name', 'Admin Kedua')
            ->set('email', 'admin2@housinghub.id')
            ->set('role_id', (string) $superAdminRole->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('super_admin', User::where('email', 'admin2@housinghub.id')->firstOrFail()?->role->slug);
    }

    public function test_all_admin_pages_render_for_super_admin(): void
    {
        $admin = $this->admin();

        $uris = [
            '/admin/dashboard',
            '/admin/housing-estates',
            '/admin/blocks',
            '/admin/houses',
            '/admin/houses/create',
            '/admin/residents',
            '/admin/residents/create',
            '/admin/users',
            '/admin/roles',
            '/admin/ipl/rates',
            '/admin/ipl/generate',
            '/admin/ipl/billings',
            '/admin/ipl/payments',
            '/admin/info/announcements',
            '/admin/info/complaints',
            '/admin/forum',
            '/admin/activity-logs',
        ];

        foreach ($uris as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_every_admin_route_is_guarded_by_permission_middleware(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach ($routes as $route) {
            if (! str_starts_with($route->uri(), 'admin')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertTrue(
                collect($middleware)->contains(fn ($item) => str_starts_with((string) $item, 'permission:')),
                'Route '.$route->uri().' belum dilindungi middleware permission.'
            );
        }
    }

    public function test_login_redirects_role_without_access_to_error(): void
    {
        $role = Role::create([
            'name' => 'Tanpa Akses',
            'slug' => 'tanpa_akses',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'tanpaakses@example.test',
            'password' => bcrypt('password123'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_bypasses_permission_check(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->hasPermission('manage-role'));
        $this->assertTrue($admin->hasAllPermissions('manage-user', 'view-activity-log'));
        $this->assertTrue($admin->hasPermission('permission-yang-tidak-ada'));
    }
}
