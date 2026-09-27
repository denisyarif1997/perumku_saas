<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ipl\Payments\Index;
use App\Livewire\Resident\Ipl\Show;
use App\Models\Billing;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\House;
use App\Models\IplRate;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use App\Services\IplBillingService;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class IplBillingTest extends TestCase
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
        // Akun platform tidak terikat estate, jadi diambil tanpa global scope
        // agar tetap ditemukan meski test sedang beractingAs sebagai user estate.
        return $this->findUserUnscoped('admin@housinghub.id');
    }

    protected function residentUser(): User
    {
        return $this->findUserUnscoped('warga.a.1@housinghub.id');
    }

    protected function makeRate(float $amount = 150000): IplRate
    {
        // Nonaktifkan tarif bawaan seeder agar tarif uji yang terpakai.
        IplRate::query()->update(['status' => 'inactive']);

        return IplRate::create([
            'name' => 'IPL Bulanan Test',
            'amount' => $amount,
            'period_type' => 'monthly',
            'effective_date' => now()->startOfYear()->toDateString(),
            'status' => 'active',
        ]);
    }

    protected function makeCashAccount(int $estateId, string $name = 'Kas Utama'): CashAccount
    {
        return CashAccount::create([
            'housing_estate_id' => $estateId,
            'name' => $name,
            'type' => 'cash',
            'opening_balance' => 0,
            'status' => 'active',
        ]);
    }

    public function test_generate_creates_one_billing_per_active_house_and_is_idempotent(): void
    {
        $this->makeRate();

        $service = app(IplBillingService::class);
        $activeHouses = House::where('status', 'active')->count();

        $first = $service->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $this->assertSame($activeHouses, $first['created']);
        $this->assertSame(0, $first['skipped']);
        $this->assertSame($activeHouses, Billing::forPeriod(now()->year, now()->month)->count());

        // Generate kedua pada periode yang sama tidak boleh membuat duplikat.
        $second = $service->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $this->assertSame(0, $second['created']);
        $this->assertSame($activeHouses, $second['skipped']);
        $this->assertSame($activeHouses, Billing::forPeriod(now()->year, now()->month)->count());
    }

    public function test_generate_without_active_rate_creates_nothing(): void
    {
        // Nonaktifkan tarif bawaan seeder.
        IplRate::query()->update(['status' => 'inactive']);

        $result = app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, Billing::count());
        $this->assertGreaterThan(0, $result['no_rate']);
    }

    public function test_generate_new_rate_same_period_creates_additional_billing(): void
    {
        $firstRate = $this->makeRate(150000);
        $service = app(IplBillingService::class);
        $activeHouses = House::where('status', 'active')->count();

        $first = $service->generate(now()->year, now()->month, null, 10, $this->admin()->id, $firstRate->id);

        $this->assertSame($activeHouses, $first['created']);

        // Tarif baru pada periode yang SAMA harus ikut tertagih (satu tagihan per tarif).
        $secondRate = IplRate::create([
            'name' => 'IPL Tambahan Test',
            'amount' => 50000,
            'period_type' => 'monthly',
            'effective_date' => now()->startOfYear()->toDateString(),
            'status' => 'active',
        ]);

        $second = $service->generate(now()->year, now()->month, null, 10, $this->admin()->id, $secondRate->id);

        $this->assertSame($activeHouses, $second['created']);
        $this->assertSame(0, $second['skipped']);
        $this->assertSame($activeHouses * 2, Billing::forPeriod(now()->year, now()->month)->count());

        // Generate ulang tarif yang sama tetap anti-duplikat.
        $third = $service->generate(now()->year, now()->month, null, 10, $this->admin()->id, $secondRate->id);

        $this->assertSame(0, $third['created']);
        $this->assertSame($activeHouses, $third['skipped']);
    }

    public function test_generate_only_bills_selected_houses(): void
    {
        $rate = $this->makeRate();
        $service = app(IplBillingService::class);
        $houseIds = House::where('status', 'active')->limit(2)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $result = $service->generate(now()->year, now()->month, null, 10, $this->admin()->id, $rate->id, $houseIds);

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, Billing::forPeriod(now()->year, now()->month)->count());
        $this->assertSame($houseIds, Billing::forPeriod(now()->year, now()->month)->pluck('house_id')->sort()->values()->all());
    }

    public function test_verified_payment_marks_billing_as_paid_and_supports_partial(): void
    {
        $this->makeRate(200000);
        app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $billing = Billing::firstOrFail();

        Payment::create([
            'payment_number' => Payment::generateNumber($billing),
            'billing_id' => $billing->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $billing->syncPaymentStatus();
        $this->assertSame('unpaid', $billing->fresh()->status);

        $billing->payments()->update(['status' => 'verified']);
        $billing->syncPaymentStatus();

        $billing->refresh();
        $this->assertSame('partial', $billing->status);
        $this->assertSame(150000.0, $billing->remaining());

        Payment::create([
            'payment_number' => Payment::generateNumber($billing),
            'billing_id' => $billing->id,
            'amount' => 150000,
            'payment_date' => now()->toDateString(),
            'status' => 'verified',
        ]);

        $billing->syncPaymentStatus();
        $billing->refresh();

        $this->assertSame('paid', $billing->status);
        $this->assertSame(0.0, $billing->remaining());
    }

    public function test_resident_cannot_view_another_residents_billing(): void
    {
        $this->makeRate();
        app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $mine = $this->residentUser();
        $other = User::where('email', 'warga.b.1@housinghub.id')->firstOrFail();

        $otherBilling = Billing::whereHas('house.houseResidents', function ($query) use ($other) {
            $query->where('resident_id', $other->resident_id);
        })->firstOrFail();

        $this->actingAs($mine)->get(route('resident.ipl.show', $otherBilling))->assertForbidden();
        $this->actingAs($other)->get(route('resident.ipl.show', $otherBilling))->assertOk();
    }

    public function test_resident_can_submit_payment_but_cannot_verify_it(): void
    {
        $this->makeRate();
        app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $resident = $this->residentUser();
        $billing = Billing::where('resident_id', $resident->resident_id)->firstOrFail();

        Livewire::actingAs($resident)
            ->test(Show::class, ['billing' => $billing])
            ->set('amount', '150000')
            ->set('payment_method', 'transfer')
            ->call('submitPayment');

        $this->assertDatabaseHas('payments', [
            'billing_id' => $billing->id,
            'status' => 'pending',
        ]);

        // Pembayaran pending belum mengubah status tagihan.
        $this->assertSame('unpaid', $billing->fresh()->status);

        // Warga tidak boleh mengakses panel verifikasi admin.
        $this->actingAs($resident)->get(route('admin.ipl.payments.index'))->assertForbidden();
    }

    public function test_admin_verification_flow_marks_billing_paid(): void
    {
        $this->makeRate();
        app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $billing = Billing::firstOrFail();
        $account = $this->makeCashAccount($billing->housing_estate_id);

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber($billing),
            'billing_id' => $billing->id,
            'amount' => (float) $billing->total,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('startVerify', $payment->id)
            ->set('cash_account_id', $account->id)
            ->call('confirmVerify')
            ->assertHasNoErrors();

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('paid', $billing->fresh()->status);

        // Verifikasi wajib mencatatkan kas masuk ke kas yang dipilih.
        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'payment_id' => $payment->id,
            'type' => CashTransaction::TYPE_IN,
            'amount' => (float) $billing->total,
        ]);
    }

    public function test_admin_verification_is_blocked_without_cash_account(): void
    {
        $this->makeRate();
        app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $billing = Billing::firstOrFail();

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber($billing),
            'billing_id' => $billing->id,
            'amount' => (float) $billing->total,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('startVerify', $payment->id)
            ->call('confirmVerify')
            ->assertHasErrors(['cash_account_id' => 'required']);

        // Tidak ada perubahan status maupun mutasi kas.
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('unpaid', $billing->fresh()->status);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_ipl_pages_are_accessible_for_both_roles(): void
    {
        $this->makeRate();
        app(IplBillingService::class)->generate(now()->year, now()->month, null, 10, $this->admin()->id);

        $resident = $this->residentUser();

        $this->actingAs($resident)->get(route('resident.dashboard'))->assertOk();
        $this->actingAs($resident)->get(route('resident.ipl.index'))->assertOk();

        $this->actingAs($this->admin())->get(route('admin.ipl.billings.index'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.ipl.rates.index'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.ipl.generate'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.ipl.payments.index'))->assertOk();
    }

    public function test_seed_data_relationships_are_consistent(): void
    {
        $resident = $this->residentUser();

        $this->assertTrue(Hash::check('password123', $resident->password));
        $this->assertSame('resident', $resident->role?->slug);
        $this->assertNotNull(Resident::find($resident->resident_id));
        $this->assertSame('resident', Role::find($resident->role_id)?->slug);
    }
}
