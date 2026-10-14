<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ipl\Billings\Show as AdminBillingShow;
use App\Livewire\Admin\Residents\Invitations;
use App\Livewire\Auth\Register;
use App\Livewire\Resident\Ipl\Show as ResidentIplShow;
use App\Livewire\Resident\Profile;
use App\Models\Billing;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\IplRate;
use App\Models\Permission;
use App\Models\Resident;
use App\Models\ResidentInvitation;
use App\Models\Role;
use App\Models\Scopes\BelongsToEstateScope;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\IplSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
        $this->seed(IplSeeder::class);
    }

    public function test_register_does_not_auto_login_pending_email(): void
    {
        $pending = User::factory()->create(['status' => 'pending']);

        Livewire::test(Register::class)
            ->set('email', $pending->email)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    protected function makeSecondEstateBilling(): array
    {
        $estate = HousingEstate::withoutGlobalScope(BelongsToEstateScope::class)
            ->firstOrCreate(['code' => 'HH-002'], ['name' => 'Estate Kedua']);

        $block = HousingBlock::firstOrCreate(
            ['housing_estate_id' => $estate->id, 'code' => 'B'],
            ['name' => 'Blok B', 'status' => 'active']
        );

        $house = House::firstOrCreate(
            ['housing_block_id' => $block->id, 'house_number' => 'B-01'],
            ['housing_estate_id' => $estate->id, 'status' => 'active']
        );

        $resident = Resident::firstOrCreate(
            ['nik' => '3273010102000002'],
            ['name' => 'Warga B', 'gender' => 'male', 'status' => 'active']
        );

        HouseResident::firstOrCreate(
            ['house_id' => $house->id, 'resident_id' => $resident->id],
            ['relationship' => 'owner', 'is_owner' => true, 'is_primary' => true,
                'start_date' => now()->toDateString(), 'status' => 'active']
        );

        $wargaB = User::firstOrCreate(
            ['email' => 'warga.b@housinghub.test'],
            ['name' => 'Warga B', 'password' => 'password',
                'role_id' => Role::where('slug', 'resident')->value('id'),
                'resident_id' => $resident->id, 'housing_estate_id' => $estate->id,
                'status' => 'active']
        );

        $billingB = Billing::withoutGlobalScope(BelongsToEstateScope::class)->create([
            'invoice_number' => 'IPL-B-SEC-0001',
            'house_id' => $house->id,
            'resident_id' => $resident->id,
            'ipl_rate_id' => IplRate::withoutGlobalScope(BelongsToEstateScope::class)->firstOrFail()->id,
            'period_month' => 9,
            'period_year' => 2026,
            'amount' => 100000,
            'total' => 100000,
            'paid_amount' => 0,
            'due_date' => now()->toDateString(),
            'status' => 'unpaid',
        ]);

        return [$estate, $house, $resident, $wargaB, $billingB];
    }

    public function test_resident_cannot_pay_other_estate_billing(): void
    {
        $billingA = Billing::firstOrFail();
        [, , , $wargaA] = $this->estateAContext($billingA);
        [, , , , $billingB] = $this->makeSecondEstateBilling();

        // Langsung render komponen milik estate B sebagai warga A → harus 403.
        Livewire::actingAs($wargaA)
            ->test(ResidentIplShow::class, ['billing' => $billingB])
            ->assertForbidden();
    }

    /** Ambil konteks estate billing seeder: estate, house, resident, user warga. */
    protected function estateAContext(Billing $billing): array
    {
        $billing->loadMissing(['house', 'resident']);
        $estate = HousingEstate::withoutGlobalScope(BelongsToEstateScope::class)
            ->findOrFail($billing->housing_estate_id);
        $warga = User::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('resident_id', $billing->resident_id)->firstOrFail();

        return [$estate, $billing->house, $billing->resident, $warga];
    }

    public function test_verify_only_user_cannot_reactivate_cancelled_billing(): void
    {
        $billing = Billing::firstOrFail();
        $billing->update(['status' => 'cancelled']);

        // Role khusus: verify-payment saja, tanpa manage-billing.
        $role = Role::create(['name' => 'Verifier Saja', 'slug' => 'verifier_saja', 'status' => 'active']);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['access-admin', 'verify-payment'])->pluck('id')->all()
        );
        $verifier = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
            'housing_estate_id' => $billing->housing_estate_id,
        ]);

        $this->assertTrue($verifier->hasPermission('verify-payment'));
        $this->assertFalse($verifier->hasPermission('manage-billing'));

        Livewire::actingAs($verifier)
            ->test(AdminBillingShow::class, ['billing' => $billing])
            ->call('activeBilling')
            ->assertForbidden();

        $this->assertSame('cancelled', $billing->fresh()->status);
    }

    public function test_admin_cannot_revoke_invitation_from_other_estate(): void
    {
        $houseA = House::firstOrFail();
        $adminA = User::create([
            'name' => 'Estate Admin A',
            'email' => 'admin.a.sec@housinghub.test',
            'password' => 'password',
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'housing_estate_id' => $houseA->housing_estate_id,
            'status' => 'active',
        ]);

        [$estateB, $houseB] = $this->makeSecondEstateBilling();

        $inviterB = User::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('housing_estate_id', $estateB->id)->firstOrFail();

        $invitationB = ResidentInvitation::create([
            'housing_estate_id' => $estateB->id,
            'house_id' => $houseB->id,
            'invited_by' => $inviterB->id,
            'name' => 'Tamu B',
            'token_hash' => Hash::make('token-b'),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($adminA)
            ->test(Invitations::class)
            ->call('revoke', $invitationB->id)
            ->assertForbidden();

        $this->assertSame('pending', $invitationB->fresh()->status);
    }

    public function test_profile_password_change_requires_current_password(): void
    {
        $user = User::withoutGlobalScope(BelongsToEstateScope::class)
            ->whereNotNull('resident_id')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('new_password', 'barupassword123')
            ->set('new_password_confirmation', 'barupassword123')
            ->call('save')
            ->assertHasErrors(['current_password']);
    }
}
