<?php

namespace Tests\Feature;

use App\Livewire\Admin\Inventory\Items as AdminItems;
use App\Livewire\Admin\Inventory\Loans as AdminLoans;
use App\Livewire\Resident\Inventory\Index as ResidentInventory;
use App\Models\HousingEstate;
use App\Models\InventoryItem;
use App\Models\ItemLoan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryLoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function resident(string $email = 'warga.a.1@housinghub.id'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Admin estate yang memegang permission manage-inventory (bukan super_admin). */
    protected function inventoryManager(): User
    {
        $role = Role::where('slug', '!=', 'super_admin')
            ->whereHas('permissions', fn ($query) => $query->where('slug', 'manage-inventory'))
            ->firstOrFail();

        return User::create([
            'name' => 'Pengelola Inventaris',
            'email' => 'inventory.manager@housinghub.test',
            'password' => 'password',
            'role_id' => $role->id,
            'housing_estate_id' => $this->resident()->housing_estate_id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::factory()->create($overrides);
    }

    protected function makeLoan(User $requester, InventoryItem $item, string $status = 'requested'): ItemLoan
    {
        return ItemLoan::factory()->create([
            'housing_estate_id' => $requester->housing_estate_id,
            'inventory_item_id' => $item->id,
            'resident_id' => $requester->resident_id,
            'user_id' => $requester->id,
            'status' => $status,
        ]);
    }

    public function test_resident_can_request_an_available_item(): void
    {
        $resident = $this->resident();
        $item = $this->makeItem(['quantity' => 2]);

        Livewire::actingAs($resident)
            ->test(ResidentInventory::class)
            ->call('requestLoan', $item->id)
            ->set('purpose', 'Untuk arisan warga')
            ->call('submit')
            ->assertHasNoErrors();

        $loan = ItemLoan::firstWhere('inventory_item_id', $item->id);

        $this->assertNotNull($loan);
        $this->assertSame('requested', $loan->status);
        $this->assertSame($resident->resident_id, $loan->resident_id);
        $this->assertSame('Untuk arisan warga', $loan->purpose);
    }

    public function test_resident_cannot_request_a_non_loanable_item(): void
    {
        $resident = $this->resident();
        $item = $this->makeItem(['status' => 'maintenance', 'quantity' => 5]);

        $this->assertFalse($item->isLoanable());

        Livewire::actingAs($resident)
            ->test(ResidentInventory::class)
            ->call('requestLoan', $item->id)
            ->assertStatus(422);
    }

    public function test_resident_cannot_request_an_item_with_no_stock_left(): void
    {
        $resident = $this->resident();
        $item = $this->makeItem(['quantity' => 1]);

        // Satu-satunya unit sedang dipinjam warga lain.
        ItemLoan::factory()->create([
            'inventory_item_id' => $item->id,
            'status' => 'loaned',
        ]);

        $this->assertSame(0, $this->withoutEstateScope($item)->availableQuantity());

        Livewire::actingAs($resident)
            ->test(ResidentInventory::class)
            ->call('requestLoan', $item->id)
            ->assertStatus(422);
    }

    public function test_resident_cannot_request_without_a_purpose(): void
    {
        $resident = $this->resident();
        $item = $this->makeItem(['quantity' => 3]);

        Livewire::actingAs($resident)
            ->test(ResidentInventory::class)
            ->call('requestLoan', $item->id)
            ->call('submit')
            ->assertHasErrors(['purpose' => 'required']);
    }

    public function test_manager_can_walk_a_loan_through_the_full_lifecycle(): void
    {
        $manager = $this->inventoryManager();
        $resident = $this->resident();
        $item = $this->makeItem(['quantity' => 1]);

        $loan = $this->makeLoan($resident, $item);

        Livewire::actingAs($manager)->test(AdminLoans::class)->call('approve', $loan->id);
        $this->assertSame('approved', $this->withoutEstateScope($loan)->status);

        Livewire::actingAs($manager)->test(AdminLoans::class)->call('handOver', $loan->id);
        $loan = $this->withoutEstateScope($loan);
        $this->assertSame('loaned', $loan->status);
        $this->assertNotNull($loan->loaned_at);

        // Selama dipinjam, unit tidak boleh dipinjam warga lain.
        $this->assertFalse($this->withoutEstateScope($item)->isLoanable());

        Livewire::actingAs($manager)->test(AdminLoans::class)
            ->call('openReturn', $loan->id)
            ->set('returnNote', 'Barang kembali utuh')
            ->call('confirmReturn', $loan->id);

        $loan = $this->withoutEstateScope($loan);
        $this->assertSame('returned', $loan->status);
        $this->assertSame('Barang kembali utuh', $loan->return_note);
        $this->assertNotNull($loan->returned_at);

        // Setelah kembali, stok tersedia lagi.
        $this->assertTrue($this->withoutEstateScope($item)->isLoanable());
    }

    public function test_manager_can_reject_a_request_without_handing_over(): void
    {
        $manager = $this->inventoryManager();
        $loan = $this->makeLoan($this->resident(), $this->makeItem());

        Livewire::actingAs($manager)->test(AdminLoans::class)->call('reject', $loan->id);

        $loan = $this->withoutEstateScope($loan);
        $this->assertSame('rejected', $loan->status);
        $this->assertNull($loan->loaned_at);
    }

    public function test_manager_cannot_skip_the_approval_step(): void
    {
        $manager = $this->inventoryManager();
        $loan = $this->makeLoan($this->resident(), $this->makeItem());

        // Pengajuan yang belum disetujui tidak boleh bisa langsung diserahkan.
        Livewire::actingAs($manager)
            ->test(AdminLoans::class)
            ->call('handOver', $loan->id)
            ->assertForbidden();

        $this->assertSame('requested', $this->withoutEstateScope($loan)->status);
    }

    public function test_a_returned_loan_cannot_be_processed_again(): void
    {
        $manager = $this->inventoryManager();
        $loan = $this->makeLoan($this->resident(), $this->makeItem(), 'returned');

        Livewire::actingAs($manager)
            ->test(AdminLoans::class)
            ->call('approve', $loan->id)
            ->assertForbidden();

        $this->assertSame('returned', $this->withoutEstateScope($loan)->status);
    }

    public function test_resident_can_cancel_only_their_own_pending_request(): void
    {
        $resident = $this->resident();
        $loan = $this->makeLoan($resident, $this->makeItem());

        Livewire::actingAs($resident)
            ->test(ResidentInventory::class)
            ->call('cancel', $loan->id);

        $this->assertSame('cancelled', $this->withoutEstateScope($loan)->status);
    }

    public function test_resident_cannot_cancel_someone_elses_request(): void
    {
        $owner = $this->resident();
        $other = $this->resident('warga.a.2@housinghub.id');
        $loan = $this->makeLoan($owner, $this->makeItem());

        Livewire::actingAs($other)
            ->test(ResidentInventory::class)
            ->call('cancel', $loan->id)
            ->assertForbidden();

        $this->assertSame('requested', $this->withoutEstateScope($loan)->status);
    }

    public function test_resident_cannot_cancel_after_the_loan_was_approved(): void
    {
        $resident = $this->resident();
        $loan = $this->makeLoan($resident, $this->makeItem(), 'approved');

        Livewire::actingAs($resident)
            ->test(ResidentInventory::class)
            ->call('cancel', $loan->id)
            ->assertForbidden();

        $this->assertSame('approved', $this->withoutEstateScope($loan)->status);
    }

    public function test_manager_can_create_and_update_an_item(): void
    {
        $manager = $this->inventoryManager();

        Livewire::actingAs($manager)->test(AdminItems::class)
            ->call('createItem')
            ->set('name', 'Kursi Pesta')
            ->set('code', 'KRS-001')
            ->set('quantity', '40')
            ->set('unit', 'buah')
            ->set('location', 'Gudang A')
            ->call('save')
            ->assertHasNoErrors();

        $item = InventoryItem::firstWhere('code', 'KRS-001');
        $this->assertNotNull($item);
        $this->assertSame('Kursi Pesta', $item->name);
        $this->assertSame(40, $item->quantity);

        Livewire::actingAs($manager)->test(AdminItems::class)
            ->call('edit', $item->id)
            ->set('name', 'Kursi Pesta Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Kursi Pesta Baru', $this->withoutEstateScope($item)->name);
    }

    public function test_item_code_must_be_unique(): void
    {
        $manager = $this->inventoryManager();
        $this->makeItem(['code' => 'DUP-001']);

        Livewire::actingAs($manager)->test(AdminItems::class)
            ->call('createItem')
            ->set('name', 'Barang Kembar')
            ->set('code', 'DUP-001')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_item_being_loaned_cannot_be_deleted(): void
    {
        $manager = $this->inventoryManager();
        $item = $this->makeItem();
        $this->makeLoan($this->resident(), $item, 'loaned');

        Livewire::actingAs($manager)
            ->test(AdminItems::class)
            ->call('delete', $item->id)
            ->assertHasNoErrors()
            ->assertSee('masih dipinjam warga, tidak bisa dihapus');

        // Barang yang sedang dipinjam harus tetap ada.
        $this->assertNotNull($this->withoutEstateScope($item));
    }

    public function test_user_without_inventory_permission_is_denied(): void
    {
        // Staf admin estate yang TIDAK memegang manage-inventory (role finance).
        $estateId = $this->resident()->housing_estate_id;
        $financeRole = Role::where('slug', 'finance')->firstOrFail();

        $this->assertFalse(
            $financeRole->permissions()->where('slug', 'manage-inventory')->exists(),
            'Role finance seharusnya tidak memegang manage-inventory.'
        );

        $staff = User::create([
            'name' => 'Staf Keuangan',
            'email' => 'finance.staff@housinghub.test',
            'password' => 'password',
            'role_id' => $financeRole->id,
            'housing_estate_id' => $estateId,
            'status' => 'active',
        ]);

        Livewire::actingAs($staff)
            ->test(AdminLoans::class)
            ->assertForbidden();
    }

    public function test_items_and_loans_are_isolated_between_estates(): void
    {
        $estateA = $this->resident()->housing_estate_id;
        $estateB = HousingEstate::create([
            'name' => 'Perumahan Lain',
            'code' => 'HH-99',
            'status' => 'active',
        ]);

        $itemA = $this->makeItem(['name' => 'Kursi Milik A']);
        $loanA = $this->makeLoan($this->resident(), $itemA);

        // Staf dari perumahan lain tidak boleh melihat barang maupun pengajuan
        // dari perumahan pertama.
        $otherManager = User::create([
            'name' => 'Pengelola Estate Lain',
            'email' => 'inventory.other@housinghub.test',
            'password' => 'password',
            'role_id' => $this->inventoryManager()->role_id,
            'housing_estate_id' => $estateB->id,
            'status' => 'active',
        ]);

        $this->actingAs($otherManager);

        $this->assertNull(InventoryItem::find($itemA->id));
        $this->assertNull(ItemLoan::find($loanA->id));
    }
}
