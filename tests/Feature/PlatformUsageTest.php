<?php

namespace Tests\Feature;

use App\Livewire\Platform\Dashboard as PlatformDashboard;
use App\Models\ActivityLog;
use App\Models\HousingEstate;
use App\Models\PageView;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Support\Currency;
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

    public function test_dashboard_reports_mrr_arr_and_conversion_rate(): void
    {
        $admin = $this->platformAdmin();
        $estate = HousingEstate::withoutGlobalScopes()->where('code', 'HH-01')->firstOrFail();
        $service = app(SubscriptionService::class);

        // Satu perumahan berbayar: trial lalu membayar paket bulanan.
        $subscription = $service->startTrial($estate);
        $invoice = $service->ensureBaseInvoice($estate);
        $service->markPaid($invoice);
        $subscription->refresh();

        $expectedMrr = (float) $invoice->fresh()->total_amount;

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Pendapatan Subscription (MRR)')
            ->assertSee(Currency::rupiah($expectedMrr))
            ->assertViewHas('finance', fn (array $finance) => $finance['mrr'] === $expectedMrr)
            ->assertViewHas('finance', fn (array $finance) => $finance['arr'] === $expectedMrr * 12)
            // Estate yang membayar = terkonversi dari trial → konversi 100%.
            ->assertViewHas('finance', fn (array $finance) => $finance['conversionRate'] === 100.0)
            ->assertSee('Konversi Trial')
            ->assertSee('Churn Rate');
    }

    public function test_dashboard_breaks_down_subscription_statuses_for_pie_chart(): void
    {
        $admin = $this->platformAdmin();
        $service = app(SubscriptionService::class);

        // Satu estate berbayar dan satu masih trial.
        $paidEstate = HousingEstate::withoutGlobalScopes()->create([
            'code' => 'PIE-PAID', 'name' => 'Pie Paid Estate', 'status' => 'active',
        ]);
        $trialEstate = HousingEstate::withoutGlobalScopes()->create([
            'code' => 'PIE-TRIAL', 'name' => 'Pie Trial Estate', 'status' => 'active',
        ]);
        $service->markPaid($service->ensureBaseInvoice($paidEstate));
        $service->startTrial($trialEstate);

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Distribusi Status Langganan')
            ->assertSee('Aktif')
            ->assertSee('Trial')
            ->assertViewHas('statusBreakdown', function (array $breakdown) {
                $active = collect($breakdown)->firstWhere('label', 'Aktif');
                $trial = collect($breakdown)->firstWhere('label', 'Trial');

                return $active['count'] === 1 && $trial['count'] === 1;
            });
    }

    public function test_dashboard_lists_arrears_with_reminder_links(): void
    {
        $admin = $this->platformAdmin();
        $estate = HousingEstate::withoutGlobalScopes()->where('code', 'HH-01')->firstOrFail();
        $service = app(SubscriptionService::class);

        // Tagihan pending yang sudah lewat jatuh tempo → muncul sebagai tunggakan.
        $invoice = $service->ensureBaseInvoice($estate);
        $invoice->forceFill(['due_at' => now()->subDays(2)])->save();

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Tunggakan Subscription')
            ->assertSee($invoice->number)
            ->assertSee('Telat 2 hari')
            // Tautan pengingat terisi dari kontak perumahan (wa.me + mailto).
            ->assertSee('wa.me/62'.substr(preg_replace('/\D/', '', $estate->phone), 1), false)
            ->assertSee('mailto:'.$estate->email, false);
    }

    public function test_dashboard_maps_pages_to_readable_menu_labels(): void
    {
        $admin = $this->platformAdmin();

        PageView::factory()->count(3)->forUser($admin)->onPath('/admin/forum')->create();

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Menu Paling Sering Diakses')
            // Path dipetakan ke label menu AdminMenu, bukan ditampilkan mentah.
            ->assertSee('Forum Warga');
    }

    public function test_dashboard_reports_dau_and_mau(): void
    {
        $admin = $this->platformAdmin();

        PageView::factory()->forUser($admin)->onPath('/admin/dashboard')->create();

        Livewire::actingAs($admin)
            ->test(PlatformDashboard::class)
            ->assertSee('Pengguna Aktif (DAU/MAU)')
            ->assertViewHas('visits', fn (array $visits) => $visits['dau'] === 1)
            ->assertViewHas('visits', fn (array $visits) => $visits['uniqueVisitors'] === 1);
    }
}
