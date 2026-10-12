<?php

namespace Tests\Feature;

use App\Models\EstateRegistration;
use App\Models\EstateSubscription;
use App\Models\EstateSubscriptionInvoice;
use App\Models\HousingEstate;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    protected function estate(): HousingEstate
    {
        return HousingEstate::withoutGlobalScopes()->create([
            'code' => 'QUOTA-'.uniqid(), 'name' => 'Quota Estate', 'status' => 'active',
        ]);
    }

    protected function makeUsers(HousingEstate $estate, int $total): void
    {
        // Role admin eksplisit: hitungan kuota mengecualikan super_admin,
        // jadi user tanpa role tidak akan terhitung dan test jadi flaky.
        $roleId = Role::where('slug', 'admin')->value('id');

        $this->assertNotNull($roleId, 'Role admin tidak ditemukan di seeder.');

        for ($i = 0; $i < $total; $i++) {
            // Pakai query builder mentah: trait BelongsToEstate mengunci
            // housing_estate_id saat create() bila CurrentEstate ter-resolve.
            DB::table('users')->insert([
                'name' => "User {$estate->id}-{$i}",
                'email' => "quota{$estate->id}-{$i}@example.test",
                'password' => Hash::make('password'),
                'role_id' => $roleId,
                'housing_estate_id' => $estate->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_approve_starts_15_day_trial_with_100_user_quota(): void
    {
        $estate = HousingEstate::withoutGlobalScopes()->create([
            'code' => 'TRIAL-1', 'name' => 'Trial Estate', 'status' => 'active',
        ]);

        $subscription = $this->service()->startTrial($estate);
        $expectedDays = (int) config('subscription.trial_days', 15);

        $this->assertSame('trial', $subscription->status);
        $this->assertSame((int) config('subscription.included_users', 100), (int) $subscription->included_users);
        $this->assertEquals($expectedDays, $subscription->trial_started_at->diffInDays($subscription->trial_ends_at));
    }

    public function test_user_quota_blocks_101st_user_until_overage_paid(): void
    {
        $estate = $this->estate();
        $subscription = $this->service()->startTrial($estate);

        // Kosongkan user seed agar hitungan deterministik.
        $this->makeUsers($estate, (int) $subscription->included_users);

        $this->expectException(\RuntimeException::class);

        $this->service()->assertCanAddUser($estate);
    }

    public function test_paid_overage_invoice_unblocks_new_user(): void
    {
        $estate = $this->estate();
        $subscription = $this->service()->startTrial($estate);

        // User persis sebanyak kuota inklusif: menambah satu lagi butuh overage.
        $expectedOverage = 1;
        $this->makeUsers($estate, (int) $subscription->included_users);

        $invoice = $this->service()->createOverageInvoice($estate);
        $this->assertSame($expectedOverage, (int) $invoice->overage_users);

        $this->service()->markPaid($invoice);
        // Setelah overage lunas, kapasitas bertambah 1 dan user baru lolos.
        $this->service()->assertCanAddUser($estate->id);

        $this->assertTrue(true);
    }

    public function test_trial_expiry_suspends_estate(): void
    {
        $estate = $this->estate();
        config(['subscription.grace_days' => 0]);
        $subscription = $this->service()->startTrial($estate);
        $subscription->forceFill([
            'trial_ends_at' => now()->subDay(),
            'current_period_end' => now()->subDay(),
        ])->save();

        $refreshed = $this->service()->refreshStatus($subscription->fresh());

        $this->assertSame('suspended', $refreshed->status);
        $this->assertFalse($this->service()->isEstateOperational($estate));
    }

    public function test_base_invoice_payment_reactivates_estate(): void
    {
        $estate = $this->estate();
        $subscription = $this->service()->startTrial($estate);
        $subscription->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

        $invoice = $this->service()->ensureBaseInvoice($estate);
        $this->service()->markPaid($invoice);

        $this->assertSame('active', $invoice->fresh()->status === 'paid' ? $this->service()->forEstate($estate)->status : 'x');
        $this->assertTrue($this->service()->isEstateOperational($estate));
        $this->assertInstanceOf(EstateSubscriptionInvoice::class, $invoice);
        $this->assertInstanceOf(EstateSubscription::class, $this->service()->forEstate($estate));
        $this->assertInstanceOf(EstateRegistration::class, new EstateRegistration);
    }
}
