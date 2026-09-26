<?php

namespace Tests\Feature;

use App\Livewire\Admin\Estates\Index;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HousingEstateTest extends TestCase
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
        return User::where('email', 'admin@housinghub.id')->firstOrFail();
    }

    public function test_admin_can_create_housing_estate_when_none_exists(): void
    {
        // Aturan bisnis: hanya boleh ada 1 perumahan, jadi uji saat belum ada data.
        // Hapus berurutan sesuai ketergantungan FK (anak -> induk).
        HouseResident::query()->delete();
        House::query()->delete();
        HousingBlock::query()->delete();
        HousingEstate::query()->delete();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('code', 'HH-TEST-01')
            ->set('name', 'Perumahan Uji Baru')
            ->call('save');

        $this->assertDatabaseHas('housing_estates', [
            'code' => 'HH-TEST-01',
            'name' => 'Perumahan Uji Baru',
            'status' => 'active',
        ]);
    }

    public function test_super_admin_can_create_multiple_housing_estates(): void
    {
        // Mode SaaS: platform boleh menambah tenant housing baru.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('code', 'HH-TEST-01')
            ->set('name', 'Perumahan Uji Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('housing_estates', ['code' => 'HH-TEST-01']);
        $this->assertSame(2, HousingEstate::count());
    }

    public function test_estate_admin_cannot_manage_housing_estates(): void
    {
        // Admin estate bekerja di dalam estate-nya, tidak boleh menambah tenant.
        $adminEstate = User::create([
            'name' => 'Admin Estate',
            'email' => 'admin.estate@housinghub.test',
            'password' => 'password',
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'housing_estate_id' => HousingEstate::where('code', 'HH-01')->value('id'),
            'status' => 'active',
        ]);

        Livewire::actingAs($adminEstate)
            ->test(Index::class)
            ->set('code', 'HH-TEST-02')
            ->set('name', 'Perumah Forbidden')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('housing_estates', ['code' => 'HH-TEST-02']);
    }
}
