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
        return HousingEstate::where('code', 'HH-01')->firstOrFail();
    }

    protected function makeUsers(HousingEstate $estate, int $total): void
    {
        $roleId = Role::where('slug', 'admin')->value('id');

        for ($i = 0; $i < $total; $i++) {
            User::withoutGlobalScopes()->create([
                'name' => "User {$i}",
                'email' => "quota{$i}@example.test",
                'password' => 'password',
                'role_id' => $roleId,
                'housing_estate_id' => $estate->id,
                'status' => 'active',
            ]);
        }
    }

    public function test_approve_starts_15_day_trial_with_100_user_quota(): void
    {
        $estate = HousingEstate::withoutGlobalScopes()->create([
            'code' => 'TRIAL-1', 'name' => 'Trial Estate', 'status' => 'active',
        ]);

        $subscription = $this->service()->startTrial($estate);

        $this->assertSame('trial', $subscription->status);
        $this->assertSame(100, (int) $subscription->included_users);
        $this->assertEquals(15, $subscription->trial_started_at->diffInDays($subscription->trial_ends_at));
    }

    public function test_user_quota_blocks_101st_user_until_overage_paid(): void
    {
        $estate = $this->estate();
        $this->service()->startTrial($estate);

        // Kosongkan user seed agar hitungan deterministik.
        User::withoutGlobalScopes()->where('housing_estate_id', $estate->id)->delete();
        $this->makeUsers($estate, 100);

        $this->expectException(\RuntimeException::class);

        $this->service()->assertCanAddUser($estate);
    }

    public function test_paid_overage_invoice_unblocks_new_user(): void
    {
        $estate = $this->estate();
        $this->service()->startTrial($estate);

        User::withoutGlobalScopes()->where('housing_estate_id', $estate->id)->delete();
        $this->makeUsers($estate, 101);

        $invoice = $this->service()->createOverageInvoice($estate);
        $this->assertSame(1, (int) $invoice->overage_users);

        $this->service()->markPaid($invoice);
        $this->service()->assertCanAddUser($estate);

        $this->assertTrue(true);
    }

    public function test_trial_expiry_suspends_estate(): void
    {
        $estate = $this->estate();
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
