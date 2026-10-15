<?php

namespace Tests\Feature;

use App\Livewire\PanicAlertWatcher;
use App\Livewire\Resident\Panic;
use App\Models\EmergencyPanic;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EmergencyPanicNotification;
use App\Services\EmergencyPanicService;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class EmergencyPanicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function residentUser(): User
    {
        return $this->findUserUnscoped('warga.a.1@housinghub.id');
    }

    protected function securityUser(): User
    {
        $estateId = $this->residentUser()->housing_estate_id;

        return User::factory()->forEstate((int) $estateId)->create([
            'role_id' => Role::where('slug', 'security')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    protected function anotherResidentOfSameEstate(User $resident): User
    {
        return User::query()
            ->where('id', '!=', $resident->id)
            ->where('housing_estate_id', $resident->housing_estate_id)
            ->whereNotNull('resident_id')
            ->where('status', 'active')
            ->firstOrFail();
    }

    /**
     * Warga aktif di perumahan lain (lengkap dengan hunian + akun)
     * untuk memastikan alarm tidak bocor antar perumahan.
     */
    protected function residentUserOfOtherEstate(): User
    {
        $estate = HousingEstate::create([
            'code' => 'HH-99',
            'name' => 'Perumahan Lain',
            'address' => 'Jl. Lain No. 99',
            'status' => 'active',
        ]);

        $block = HousingBlock::create([
            'housing_estate_id' => $estate->id,
            'code' => 'Z',
            'name' => 'Blok Z',
            'status' => 'active',
        ]);

        $house = House::create([
            'housing_estate_id' => $estate->id,
            'housing_block_id' => $block->id,
            'house_number' => '1',
            'status' => 'active',
        ]);

        $resident = Resident::create([
            'nik' => '317400009999001',
            'name' => 'Warga Lain',
            'status' => 'active',
        ]);

        HouseResident::create([
            'house_id' => $house->id,
            'resident_id' => $resident->id,
            'relationship' => 'owner',
            'is_owner' => true,
            'is_primary' => true,
            'status' => 'active',
        ]);

        return User::factory()->forEstate($estate)->create([
            'resident_id' => $resident->id,
            'role_id' => Role::where('slug', 'resident')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    public function test_route_is_registered_for_residents_with_required_middleware(): void
    {
        $this->get(route('resident.panic'))
            ->assertStatus(302) // guest
            ->assertRedirect(route('login'));

        // Role buatan tanpa resident-panic harus ditolak middleware.
        $role = Role::create([
            'name' => 'Tanpa Panic',
            'slug' => 'tanpa_panic',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('resident.panic'))
            ->assertStatus(403); // without resident-panic permission
    }

    public function test_authenticated_resident_with_permission_can_open_form(): void
    {
        $this->actingAs($this->residentUser())
            ->get(route('resident.panic'))
            ->assertOk()
            ->assertSee('Alarm Darurat');
    }

    public function test_fire_creates_open_emergency_panic_and_fires_notifications(): void
    {
        Notification::fake();

        $security = $this->securityUser();
        $resident = $this->residentUser();
        $neighbour = $this->anotherResidentOfSameEstate($resident);
        $outsider = $this->residentUserOfOtherEstate();

        Livewire::actingAs($resident)
            ->test(Panic::class)
            ->set('reason', 'Banjir di depan rumah')
            ->call('fire');

        $panic = EmergencyPanic::query()->where('user_id', $resident->id)->firstOrFail();

        $this->assertSame('open', $panic->status);
        $this->assertSame((int) $resident->resident_id, (int) $panic->resident_id);
        $this->assertSame('Banjir di depan rumah', $panic->reason);
        $this->assertNull($panic->reference_type);
        $this->assertNull($panic->reference_id);
        $this->assertNull($panic->latitude);
        $this->assertNull($panic->longitude);

        Notification::assertSentTo($security, EmergencyPanicNotification::class);
        Notification::assertSentTo($neighbour, EmergencyPanicNotification::class);
        Notification::assertNotSentTo($outsider, EmergencyPanicNotification::class);
        Notification::assertNotSentTo($resident, EmergencyPanicNotification::class);
    }

    public function test_duplicate_open_panic_for_same_reason_is_prevented(): void
    {
        Notification::fake();

        $resident = $this->residentUser();

        $this->actingAs($resident);

        $service = app(EmergencyPanicService::class);

        $payload = [
            'reason' => 'Banjir',
        ];

        $first = $service->fire($payload);
        $second = $service->fire($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EmergencyPanic::query()->where('user_id', $resident->id)->count());
    }

    public function test_fire_without_reason_fails_validation(): void
    {
        $resident = $this->residentUser();

        Livewire::actingAs($resident)
            ->test(Panic::class)
            ->set('reason', '')
            ->call('fire')
            ->assertHasErrors(['reason']);

        $this->assertSame(0, EmergencyPanic::count());
    }

    public function test_notification_is_sent_to_estate_security_staff_and_same_estate_neighbours(): void
    {
        Notification::fake();

        $security = $this->securityUser();
        $resident = $this->residentUser();
        $neighbour = $this->anotherResidentOfSameEstate($resident);
        $outsider = $this->residentUserOfOtherEstate();

        $this->actingAs($resident);

        app(EmergencyPanicService::class)->fire([
            'reason' => 'Tetangga berteriak minta tolong',
        ]);

        Notification::assertSentTo($security, EmergencyPanicNotification::class);
        Notification::assertSentToTimes($security, EmergencyPanicNotification::class, 1);
        Notification::assertSentTo($neighbour, EmergencyPanicNotification::class);
        Notification::assertNotSentTo($outsider, EmergencyPanicNotification::class);
    }

    public function test_panic_alert_watcher_exposes_unread_panic_to_recipient_only(): void
    {
        $resident = $this->residentUser();
        $neighbour = $this->anotherResidentOfSameEstate($resident);

        $this->actingAs($resident);

        app(EmergencyPanicService::class)->fire(['reason' => 'Kebakaran di Blok A!']);

        Livewire::actingAs($neighbour)
            ->test(PanicAlertWatcher::class)
            ->assertSee('data-panic-watcher', false)
            ->assertSee('Kebakaran di Blok A!');

        Livewire::actingAs($resident)
            ->test(PanicAlertWatcher::class)
            ->assertDontSee('Kebakaran di Blok A!');
    }
}
