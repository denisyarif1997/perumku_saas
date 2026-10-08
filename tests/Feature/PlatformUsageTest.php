<?php

namespace Tests\Feature;

use App\Livewire\Platform\Dashboard as PlatformDashboard;
use App\Models\ActivityLog;
use App\Models\PageView;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function platformAdmin(): User
    {
        return User::whereHas('role', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
    }

    protected function resident(): User
    {
        return User::whereHas('role', fn ($query) => $query->where('slug', 'resident'))->firstOrFail();
    }

    public function test_visit_middleware_records_the_page_a_user_opened(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertDatabaseHas('page_views', [
            'user_id' => $admin->id,
            'path' => '/admin/dashboard',
        ]);
    }

    public function test_visit_middleware_ignores_repeated_visit_to_the_same_page(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Refresh atau navigasi ulang tidak boleh membuat kunjungan terduplikasi.
        $this->assertSame(1, PageView::query()->where('path', '/admin/dashboard')->count());
    }

    public function test_visit_middleware_does_not_record_failed_pages(): void
    {
        // Halaman yang tidak ada menghasilkan 404, bukan penggunaan aplikasi.
        $this->get('/halaman-yang-tidak-ada')->assertNotFound();

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_visit_middleware_records_again_after_the_dedupe_window(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Kunjungan lama di luar jendela dedupe tidak lagi memblokir pencatatan.
        PageView::query()->update(['created_at' => now()->subMinutes(PageView::DEDUPE_WINDOW_MINUTES + 5)]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(2, PageView::query()->where('path', '/admin/dashboard')->count());
    }

    public function test_dashboard_reports_visits_and_top_pages(): void
    {
        $admin = $this->platformAdmin();

        PageView::factory()->count(3)->forUser($admin)->onPath('/admin/forum')->create();
        PageView::factory()->forUser($admin)->onPath('/resident/dashboard')->create();

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Total Kunjungan')
            ->assertSee('Halaman Paling Dikunjungi')
            ->assertSee('/admin/forum')
            ->assertSee('Pengguna Paling Aktif')
            ->assertSee($admin->name);
    }

    public function test_dashboard_counts_module_activity_with_readable_labels(): void
    {
        $admin = $this->platformAdmin();

        ActivityLog::record([
            'user_id' => $admin->id,
            'action' => 'create',
            'module' => 'cash_transactions',
            'description' => 'Mencatat transaksi kas',
        ]);

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Modul Paling Aktif')
            // Slug modul diubah menjadi label, bukan ditampilkan mentah.
            ->assertSee('Transaksi Kas')
            ->assertDontSee('cash_transactions');
    }

    public function test_dashboard_ignores_visits_older_than_the_usage_window(): void
    {
        $admin = $this->platformAdmin();

        PageView::factory()->forUser($admin)->create([
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertViewHas('visits', fn (array $visits) => $visits['total'] === 0)
            // Total sepanjang waktu tetap ikut ditampilkan.
            ->assertViewHas('visits', fn (array $visits) => $visits['allTime'] === 1);
    }

    public function test_dashboard_still_lists_every_estate(): void
    {
        $admin = $this->platformAdmin();

        PageView::factory()->forUser($admin)->onPath('/admin/dashboard')->create();

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Pemakaian per Kondominan')
            ->assertSee('HousingHub Residence');
    }

    public function test_only_super_admin_sees_usage_statistics(): void
    {
        $this->actingAs($this->resident())
            ->get(route('platform.dashboard'))
            ->assertForbidden();
    }
}
