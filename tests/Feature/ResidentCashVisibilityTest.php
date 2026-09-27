<?php

namespace Tests\Feature;

use App\Livewire\Resident\Cash\Index as ResidentCashIndex;
use App\Models\Billing;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\House;
use App\Models\HousingEstate;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Scopes\BelongsToEstateScope;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman kas untuk warga: boleh lihat saldo dan riwayat, tapi tidak boleh
 * membocorkan siapa yang membayar.
 */
class ResidentCashVisibilityTest extends TestCase
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

    protected function makeCashAccount(?int $estateId, string $name, float $openingBalance): CashAccount
    {
        return CashAccount::withoutGlobalScope(BelongsToEstateScope::class)->create([
            'housing_estate_id' => $estateId,
            'name' => $name,
            'type' => 'cash',
            'opening_balance' => $openingBalance,
            'status' => 'active',
        ]);
    }

    protected function makeCashIn(CashAccount $account, float $amount, string $description = 'Iuran'): CashTransaction
    {
        return CashTransaction::withoutGlobalScope(BelongsToEstateScope::class)->create([
            'cash_account_id' => $account->id,
            'type' => CashTransaction::TYPE_IN,
            'amount' => $amount,
            'transaction_date' => now()->toDateString(),
            'category' => 'ipl',
            'description' => $description,
        ]);
    }

    public function test_resident_sees_cash_balance_and_history(): void
    {
        $estateId = HousingEstate::firstOrFail()->id;
        $kas = $this->makeCashAccount($estateId, 'Kas RT 01', 1000000);
        $this->makeCashIn($kas, 250000);

        Livewire::actingAs($this->residentUser())
            ->test(ResidentCashIndex::class)
            ->assertOk()
            ->assertSee('Kas RT 01')
            ->assertSee('Kas Masuk')
            // Saldo awal 1.000.000 + kas masuk 250.000
            ->assertSee('1.250.000')
            ->assertSee('250.000');

        $this->actingAs($this->residentUser())->get(route('resident.cash.index'))->assertOk();
    }

    public function test_resident_cash_page_never_reveals_who_paid(): void
    {
        // description memuat nama warga, payment_id bisa ditelusuri ke
        // pembayar, dan reference adalah nomor pembayaran. Ketiganya tidak
        // boleh sampai ke halaman ini.
        $kas = $this->makeCashAccount(HousingEstate::firstOrFail()->id, 'Kas RT 01', 0);

        $resident = Resident::create([
            'nik' => '3273010101999999',
            'name' => 'Budi Rahasia',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $billing = Billing::create([
            'invoice_number' => 'INV-RAHASIA-0001',
            'house_id' => House::firstOrFail()->id,
            'resident_id' => $resident->id,
            'billing_type' => 'ipl',
            'period_month' => 1,
            'period_year' => 2026,
            'amount' => 150000,
            'total' => 150000,
            'due_date' => now()->toDateString(),
            'status' => 'unpaid',
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY/20260101/INV-RAHASIA-0001-01',
            'billing_id' => $billing->id,
            'resident_id' => $resident->id,
            'amount' => 150000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'pending',
        ]);

        $transaksi = $this->makeCashIn($kas, 150000, 'Pembayaran INV-RAHASIA-0001 — Budi Rahasia');
        $transaksi->forceFill([
            'payment_id' => $payment->id,
            'reference' => $payment->payment_number,
        ])->save();

        $html = Livewire::actingAs($this->residentUser())
            ->test(ResidentCashIndex::class)
            ->assertOk()
            ->html();

        // Nama warga, nomor invoice, dan nomor pembayaran tidak boleh bocor.
        $this->assertStringNotContainsString('Budi Rahasia', $html);
        $this->assertStringNotContainsString('INV-RAHASIA-0001', $html);
        $this->assertStringNotContainsString('PAY/20260101/INV-RAHASIA-0001-01', $html);

        // Nominalnya sendiri tetap terlihat, karena itu memang tujuan halaman.
        $this->assertStringContainsString('150.000', $html);
    }

    public function test_cash_page_is_read_only_for_resident(): void
    {
        $this->makeCashAccount(HousingEstate::firstOrFail()->id, 'Kas RT 01', 0);

        Livewire::actingAs($this->residentUser())
            ->test(ResidentCashIndex::class)
            ->assertOk()
            ->assertDontSee('Catat Transaksi')
            ->assertDontSee('Tambah Kas')
            ->assertDontSee('wire:click="save"', false);
    }

    public function test_staff_without_resident_data_cannot_open_cash_page(): void
    {
        $staff = User::factory()->create([
            'role_id' => Role::where('slug', 'finance')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->assertNull($staff->resident_id);

        $this->actingAs($staff)->get(route('resident.cash.index'))->assertForbidden();
    }

    public function test_resident_cash_page_does_not_leak_another_estate(): void
    {
        $estateA = HousingEstate::firstOrFail();

        $estateB = HousingEstate::withoutGlobalScope(BelongsToEstateScope::class)->create([
            'code' => 'HH-900',
            'name' => 'Perumah Tetangga',
        ]);

        $kasA = $this->makeCashAccount($estateA->id, 'Kas RT 01', 500000);
        $this->makeCashIn($kasA, 100000);

        $kasB = $this->makeCashAccount($estateB->id, 'Kas Tetangga', 900000);
        $this->makeCashIn($kasB, 700000);

        $html = Livewire::actingAs($this->residentUser())
            ->test(ResidentCashIndex::class)
            ->assertOk()
            ->assertSee('Kas RT 01')
            ->assertDontSee('Kas Tetangga')
            ->html();

        // Nominal estate B tidak ikut terhitung ke total kas yang dilihat warga.
        $this->assertStringContainsString('600.000', $html);
        $this->assertStringNotContainsString('1.600.000', $html);
    }
}
