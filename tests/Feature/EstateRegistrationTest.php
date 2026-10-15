<?php

namespace Tests\Feature;

use App\Livewire\Auth\Onboarding;
use App\Livewire\Auth\Register;
use App\Models\EstateRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\EstateRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EstateRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_email_creates_pending_user_and_redirects_to_onboarding(): void
    {
        $this->seed();

        Livewire::test(Register::class)
            ->set('email', 'calon@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding'));

        $user = User::where('email', 'calon@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->housing_estate_id);
        $this->assertSame('admin', $user->role->slug);
        $this->assertAuthenticated();
    }

    public function test_onboarding_saves_staging_and_keeps_user_pending(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'email' => 'calon@example.com',
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'status' => 'pending',
        ]);
        $originalName = $user->name;
        $originalPhone = $user->phone;
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Calon Admin')
            ->set('email', 'calon@example.com')
            ->set('phone', '081234567890')
            ->set('estateCode', 'GARDEN-CITY')
            ->set('estateName', 'Garden City')
            ->set('blockCode', 'A')
            ->set('houseNumber', '12')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estate_registrations', [
            'user_id' => $user->id,
            'method' => 'email',
            'name' => 'Calon Admin',
            'phone' => '081234567890',
            'estate_code' => 'GARDEN-CITY',
            'status' => 'pending',
        ]);
        $this->assertSame('pending', $user->fresh()->status);
        // Data diri calon TIDAK ditulis ke tabel users saat onboarding; hanya
        // tersimpan di staging sampai superadmin menyetujui.
        $this->assertSame($originalName, $user->fresh()->name);
        $this->assertSame($originalPhone, $user->fresh()->phone);
        // Belum inject ke produksi sebelum approve.
        $this->assertDatabaseMissing('housing_estates', ['code' => 'GARDEN-CITY']);
    }

    public function test_superadmin_approve_injects_estate_resident_and_admin(): void
    {
        $this->seed();
        $roleId = Role::where('slug', 'admin')->value('id');
        $applicant = User::factory()->create([
            'email' => 'calon@example.com',
            'role_id' => $roleId,
            'status' => 'pending',
        ]);
        $registration = EstateRegistration::create([
            'user_id' => $applicant->id,
            'method' => 'email',
            'name' => 'Calon Admin',
            'email' => 'calon@example.com',
            'phone' => '081234567890',
            'estate_code' => 'GARDEN-CITY',
            'estate_name' => 'Garden City',
            'block_code' => 'A',
            'block_name' => 'Blok Anggrek',
            'house_number' => '12',
            'status' => 'pending',
        ]);
        $super = User::factory()->withoutEstate()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'status' => 'active',
        ]);

        $result = app(EstateRegistrationService::class)->approve($registration, $super);

        $this->assertSame('approved', $registration->fresh()->status);
        $this->assertDatabaseHas('housing_estates', ['code' => 'GARDEN-CITY', 'name' => 'Garden City']);
        $this->assertDatabaseHas('residents', ['email' => 'calon@example.com', 'name' => 'Calon Admin']);
        $this->assertDatabaseHas('houses', ['house_number' => '12']);

        $applicant = $applicant->fresh();
        $this->assertSame('active', $applicant->status);
        $this->assertSame('admin', $applicant->role->slug);
        $this->assertSame($result['estate']->id, $applicant->housing_estate_id);
        $this->assertNotNull($applicant->resident_id);
    }

    public function test_superadmin_reject_marks_registration_rejected(): void
    {
        $this->seed();
        $applicant = User::factory()->create(['status' => 'pending']);
        $registration = EstateRegistration::create([
            'user_id' => $applicant->id,
            'method' => 'email',
            'name' => 'Calon',
            'email' => $applicant->email,
            'phone' => '081234567890',
            'estate_code' => 'GARDEN-CITY',
            'estate_name' => 'Garden City',
            'status' => 'pending',
        ]);
        $super = User::factory()->withoutEstate()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'status' => 'active',
        ]);

        app(EstateRegistrationService::class)->reject($registration, $super, 'Data kurang lengkap.');

        $this->assertSame('rejected', $registration->fresh()->status);
        $this->assertSame('pending', $applicant->fresh()->status);
        $this->assertDatabaseMissing('housing_estates', ['code' => 'GARDEN-CITY']);
    }
}
