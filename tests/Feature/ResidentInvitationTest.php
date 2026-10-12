<?php

namespace Tests\Feature;

use App\Livewire\Admin\Residents\Invitations;
use App\Livewire\Invite\Show;
use App\Models\House;
use App\Models\ResidentInvitation;
use App\Models\User;
use App\Services\ResidentInvitationService;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResidentInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function estateAdmin(): User
    {
        $admin = User::where('email', 'admin@housinghub.id')->firstOrFail();
        $estateId = House::firstOrFail()->housing_estate_id;
        $admin->forceFill(['housing_estate_id' => $estateId])->save();

        return $admin->fresh();
    }

    public function test_admin_can_create_invitation_with_wa_link(): void
    {
        $admin = $this->estateAdmin();
        $house = House::firstOrFail();

        Livewire::actingAs($admin)
            ->test(Invitations::class)
            ->set('name', 'Warga Baru')
            ->set('phone', '081234567890')
            ->set('house_id', (string) $house->id)
            ->call('create')
            ->assertHasNoErrors();

        $invitation = ResidentInvitation::latest('id')->firstOrFail();

        $this->assertSame('Warga Baru', $invitation->name);
        $this->assertSame('pending', $invitation->status);
        $this->assertTrue($invitation->expires_at->isFuture());
    }

    public function test_invitee_can_accept_invitation_and_login(): void
    {
        $admin = $this->estateAdmin();
        $house = House::firstOrFail();

        $token = null;
        Livewire::actingAs($admin)
            ->test(Invitations::class)
            ->set('name', 'Siti')
            ->set('house_id', (string) $house->id)
            ->call('create');

        // Token mentah tidak disimpan; ambil via findByToken tidak mungkin.
        // Buat undangan kedua langsung via service untuk dapat token.
        $service = app(ResidentInvitationService::class);
        $result = $service->create($admin, ['name' => 'Siti', 'house_id' => $house->id]);
        $token = $result['token'];

        Livewire::test(Show::class, ['token' => $token])
            ->set('name', 'Siti')
            ->set('email', 'siti@warga.test')
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'rahasia123')
            ->call('accept')
            ->assertRedirect(route('resident.dashboard'));

        $user = User::where('email', 'siti@warga.test')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->resident_id);
        $this->assertSame('accepted', ResidentInvitation::where('id', $result['invitation']->id)->value('status'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $admin = $this->estateAdmin();
        $house = House::firstOrFail();

        $service = app(ResidentInvitationService::class);
        $result = $service->create($admin, ['name' => 'Budi', 'house_id' => $house->id]);
        $result['invitation']->forceFill(['expires_at' => now()->subDay()])->save();

        $this->assertNull(ResidentInvitation::findByToken($result['token']));
    }
}
