<?php

namespace Tests\Feature;

use App\Livewire\Admin\Blocks\Index as BlockIndex;
use App\Livewire\Auth\Register;
use App\Livewire\Resident\Complaints\Index as ResidentComplaintsIndex;
use App\Models\Billing;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Complaint;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Scopes\BelongsToEstateScope;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function estateA(): HousingEstate
    {
        return HousingEstate::where('code', 'HH-01')->firstOrFail();
    }

    /** Warga seed estate A (blok A, rumah 1). */
    protected function residentA(): User
    {
        return User::where('email', 'warga.a.1@housinghub.id')->firstOrFail();
    }

    /**
     * Admin operasional yang terikat ke estate A.
     * Akun admin@housinghub.id dari seeder adalah super_admin platform
     * (selalu tanpa filter estate), jadi tidak bisa dipakai di sini.
     */
    protected function estateAdminA(): User
    {
        return $this->makeStaff('admin.a@housinghub.test', 'admin', $this->estateA()->id);
    }

    /**
     * Admin estate B — dipakai untuk membuktikan baris milik estate B sendiri
     * tetap terlihat setelah kolom estate dinormalisasi.
     */
    protected function estateAdminB(): User
    {
        return $this->makeStaff('admin.b@housinghub.test', 'admin', $this->estateBId());
    }

    /**
     * Super admin platform. Sengaja dibuat tanpa housing_estate_id supaya
     * CurrentEstate::id() bernilai null (mode tanpa filter).
     */
    protected function platformSuperAdmin(): User
    {
        return User::create([
            'name' => 'Platform Operator',
            'email' => 'platform@housinghub.test',
            'password' => 'password',
            'role_id' => $this->roleId('super_admin'),
            'status' => 'active',
        ]);
    }

    /** Staf estate B yang ikut memegang permission tertentu. */
    protected function staffB(string $permission): User
    {
        return $this->makeStaff('staf.b@housinghub.test', $this->roleWithPermission($permission), $this->estateBId());
    }

    protected function makeStaff(string $email, string $roleSlug, int $estateId): User
    {
        return User::create([
            'name' => 'Staf '.$roleSlug,
            'email' => $email,
            'password' => 'password',
            'role_id' => $this->roleId($roleSlug),
            'housing_estate_id' => $estateId,
            'status' => 'active',
        ]);
    }

    /**
     * Buat akun kas. housing_estate_id null berarti kas bersama yang dipakai
     * seluruh perumahan.
     */
    protected function makeCashAccount(?int $estateId, string $name, float $openingBalance): CashAccount
    {
        // Dibuat tanpa filter estate: pemanggil sering sudah beractingAs
        // sebagai user estate lain, sehingga baris estate A tidak terlihat.
        return CashAccount::withoutGlobalScope(BelongsToEstateScope::class)->create([
            'housing_estate_id' => $estateId,
            'name' => $name,
            'type' => 'cash',
            'opening_balance' => $openingBalance,
            'status' => 'active',
        ]);
    }

    protected function makeCashIn(CashAccount $account, float $amount): CashTransaction
    {
        return CashTransaction::withoutGlobalScope(BelongsToEstateScope::class)->create([
            'cash_account_id' => $account->id,
            'type' => CashTransaction::TYPE_IN,
            'amount' => $amount,
            'transaction_date' => now()->toDateString(),
            'category' => 'ipl',
            'description' => 'Iuran '.now()->format('F Y'),
        ]);
    }

    /**
     * Role non-platform yang punya permission tertentu. Super admin sengaja
     * dikecualikan karena ia selalu lolos filter estate, sehingga tidak bisa
     * dipakai sebagai staf tenant pada test ini.
     */
    protected function roleWithPermission(string $permission): string
    {
        $role = Role::where('slug', '!=', 'super_admin')
            ->whereHas('permissions', fn ($q) => $q->where('slug', $permission))
            ->first();

        return $role?->slug ?? 'maintenance';
    }

    /**
     * ID estate B. Dibaca tanpa filter estate karena pemanggilnya sering
     * sudah beractingAs sebagai user estate lain.
     */
    protected function estateBId(): int
    {
        $id = HousingEstate::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('code', 'HH-002')
            ->value('id');

        return $id ?? $this->makeEstateB()[0]->id;
    }

    /**
     * Buat estate kedua lengkap dengan blok, rumah, dan warga aktifnya,
     * lalu kembalikan array [estate, house, resident, user].
     */
    protected function makeEstateB(): array
    {
        // Idempotent: estate B boleh dipanggil berkali-kali dalam satu test,
        // dan tidak boleh bergantung pada sesi estate yang sedang aktif.
        $estate = HousingEstate::withoutGlobalScope(BelongsToEstateScope::class)->firstOrCreate(
            ['code' => 'HH-002'],
            ['name' => 'Perumah Sejahtera']
        );

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
            ['name' => 'Warga Estate B', 'gender' => 'male', 'status' => 'active']
        );

        HouseResident::firstOrCreate(
            ['house_id' => $house->id, 'resident_id' => $resident->id],
            [
                'relationship' => 'owner',
                'is_owner' => true,
                'is_primary' => true,
                'start_date' => now()->subYear(),
                'status' => 'active',
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'warga.b@housinghub.test'],
            [
                'name' => 'Warga B',
                'password' => 'password',
                'role_id' => $this->roleId('resident'),
                'resident_id' => $resident->id,
                'housing_estate_id' => $estate->id,
                'status' => 'active',
            ]
        );

        return [$estate, $house, $resident, $user];
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    /**
     * Warga kedua di estate B: punya hunian sendiri sehingga benar-benar
     * punya konteks estate, tapi bukan pemilik tagihan yang diuji.
     */
    protected function makeResidentOfEstateB(string $email, string $nik): User
    {
        $estateId = $this->estateBId();
        $blockId = HousingBlock::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('housing_estate_id', $estateId)
            ->value('id');

        $house = House::firstOrCreate(
            ['housing_block_id' => $blockId, 'house_number' => 'B-02'],
            ['housing_estate_id' => $estateId, 'status' => 'active']
        );

        $resident = Resident::create([
            'nik' => $nik,
            'name' => 'Warga Kedua Estate B',
            'gender' => 'female',
            'status' => 'active',
        ]);

        HouseResident::create([
            'house_id' => $house->id,
            'resident_id' => $resident->id,
            'relationship' => 'owner',
            'is_owner' => true,
            'is_primary' => true,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Warga Kedua B',
            'email' => $email,
            'password' => 'password',
            'role_id' => $this->roleId('resident'),
            'resident_id' => $resident->id,
            'housing_estate_id' => $estateId,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_admin_of_estate_only_sees_own_estate_data(): void
    {
        [, , , $wargaB] = $this->makeEstateB();
        $adminA = $this->estateAdminA();

        $this->actingAs($adminA);

        $this->assertSame(1, HousingEstate::count());
        $this->assertNull(House::where('house_number', 'B-01')->first());
        $this->assertNull(Resident::where('name', 'Warga Estate B')->first());
        $this->assertNull(User::find($wargaB->id));
    }

    public function test_resident_of_estate_b_only_sees_own_estate_data(): void
    {
        [, , , $wargaB] = $this->makeEstateB();
        $estateAId = $this->estateA()->id;

        $this->actingAs($wargaB);

        $this->assertSame(1, HousingEstate::count());
        $this->assertNull(House::where('housing_estate_id', $estateAId)->first());
        $this->assertNull(Resident::where('name', 'Warga A-1')->first());
    }

    public function test_forum_posts_are_scoped_per_estate(): void
    {
        [, , , $wargaB] = $this->makeEstateB();

        $wargaA = $this->residentA();
        $wargaA->forceFill(['housing_estate_id' => $this->estateA()->id])->save();

        $postA = Post::create([
            'housing_estate_id' => $this->estateA()->id,
            'user_id' => $wargaA->id,
            'resident_id' => $wargaA->resident_id,
            'title' => 'Post A',
            'body' => 'Isi A',
        ]);

        $postB = Post::create([
            'housing_estate_id' => $wargaB->housing_estate_id,
            'user_id' => $wargaB->id,
            'resident_id' => $wargaB->resident_id,
            'title' => 'Post B',
            'body' => 'Isi B',
        ]);

        $this->actingAs($wargaA);
        $this->assertNotNull(Post::find($postA->id));
        $this->assertNull(Post::find($postB->id));

        $this->actingAs($wargaB);
        $this->assertNotNull(Post::find($postB->id));
        $this->assertNull(Post::find($postA->id));
    }

    public function test_cash_transactions_of_shared_account_stay_visible(): void
    {
        // Akun kas boleh dibuat tanpa memilih perumahan, yaitu
        // housing_estate_id NULL = kas bersama untuk seluruh perumahan. Aturan
        // isolasi tenant tetap memberi baris global kepada penghuni estate
        // (sama seperti CashAccount sendiri), jadi mutasinya juga harus ikut
        // terlihat — kalau tidak, akunnya tampil di Daftar Kas tetapi
        // riwayatnya hilang dan Saldo Sekarang hanya menghitung saldo awal.
        $adminA = $this->estateAdminA();
        $estateAId = $this->estateA()->id;

        $akunA = $this->makeCashAccount($estateAId, 'Kas RT 01', 1000000);
        $akunShared = $this->makeCashAccount(null, 'Kas Bersama', 500000);

        $this->makeCashIn($akunA, 250000);
        $this->makeCashIn($akunShared, 300000);

        $this->actingAs($adminA);

        // Kedua akun terlihat, dan transaksi keduanya ikut terbawa.
        $this->assertNotNull(CashAccount::find($akunShared->id));
        $this->assertSame(2, CashTransaction::count());
        $this->assertNotNull(CashTransaction::where('cash_account_id', $akunShared->id)->first());

        // Saldo kas bersama wajib ikut menghitung transaksinya sendiri.
        $this->assertSame(800000.0, CashAccount::find($akunShared->id)->currentBalance());
        $this->assertSame(1250000.0, CashAccount::find($akunA->id)->currentBalance());
    }

    public function test_cash_transactions_of_another_estate_account_stay_hidden(): void
    {
        // Larangan bocor antar-hook: orWhereNull di scope CashTransaction hanya
        // boleh membuka baris GLOBAL, tidak boleh membuka akun estate lain.
        [, , , $wargaB] = $this->makeEstateB();
        $estateBId = $wargaB->housing_estate_id;

        $akunShared = $this->makeCashAccount(null, 'Kas Bersama', 500000);
        $akunB = $this->makeCashAccount($estateBId, 'Kas Estate B', 750000);

        $this->makeCashIn($akunShared, 300000);
        $transaksiB = $this->makeCashIn($akunB, 900000);

        $this->actingAs($this->estateAdminA());

        $this->assertNotNull(CashTransaction::where('cash_account_id', $akunShared->id)->first());
        $this->assertNull(CashTransaction::find($transaksiB->id));
        $this->assertNull(CashAccount::find($akunB->id));

        // Bolak-balik: penghuni estate B tetap melihat kasnya sendiri.
        $this->actingAs($wargaB);
        $this->assertNotNull(CashTransaction::find($transaksiB->id));
    }

    public function test_estate_admin_cannot_write_rows_into_another_estate(): void
    {
        [, , , $wargaB] = $this->makeEstateB();
        $estateAId = $this->estateA()->id;
        $estateBId = $wargaB->housing_estate_id;

        $this->actingAs($this->estateAdminA());

        // housing_estate_id dari input dipaksa menjadi estate admin.
        $block = HousingBlock::create([
            'housing_estate_id' => $estateBId,
            'code' => 'X',
            'name' => 'Blok dari Estate B',
            'status' => 'active',
        ]);

        $this->assertSame($estateAId, $block->fresh()->housing_estate_id);
    }

    public function test_staff_without_estate_cannot_create_shared_rows(): void
    {
        $this->makeEstateB();

        $tanpaEstate = User::create([
            'name' => 'Staf Tanpa-Perumahan',
            'email' => 'tanpa.estate@housinghub.test',
            'password' => 'password',
            'role_id' => $this->roleId('rt'),
            'housing_estate_id' => null,
            'status' => 'active',
        ]);

        $this->actingAs($tanpaEstate);

        $this->expectException(HttpException::class);

        HousingBlock::create([
            'housing_estate_id' => null,
            'code' => 'Y',
            'name' => 'Blok Global',
            'status' => 'active',
        ]);
    }

    public function test_block_form_cannot_write_into_another_estate_via_livewire(): void
    {
        [, , , $wargaB] = $this->makeEstateB();
        $estateAId = $this->estateA()->id;
        $estateBId = $wargaB->housing_estate_id;

        // Admin estate A memalsukan form agar menulis ke estate B.
        Livewire::actingAs($this->estateAdminA())
            ->test(BlockIndex::class)
            ->set('housing_estate_id', (string) $estateBId)
            ->set('code', 'Z')
            ->set('name', 'Blok Palsu')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('housing_blocks', ['code' => 'Z', 'housing_estate_id' => $estateBId]);
        $this->assertDatabaseHas('housing_blocks', ['code' => 'Z', 'housing_estate_id' => $estateAId]);
    }

    public function test_estate_dropdown_only_offers_own_estate(): void
    {
        $this->makeEstateB();

        $component = Livewire::actingAs($this->estateAdminA())->test(BlockIndex::class);

        // Dropdown di-feed HousingEstate::orderBy('name')->get() yang ikut
        // ter-scope, jadi estate lain tidak boleh ikut terbawa.
        $this->assertSame([$this->estateA()->id], $component->viewData('estates')->pluck('id')->all());
    }

    public function test_guest_can_register_new_estate_with_its_own_admin(): void
    {
        $this->get(route('register'))->assertOk();

        Livewire::test(Register::class)
            ->set('estateCode', 'garden-city')
            ->set('estateName', 'Garden City Residence')
            ->set('name', 'Budi Santoso')
            ->set('email', 'budi@garden.test')
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'rahasia123')
            ->call('register')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('housing_estates', [
            'code' => 'GARDEN-CITY',
            'name' => 'Garden City Residence',
        ]);

        $admin = User::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('email', 'budi@garden.test')->firstOrFail();
        $estate = HousingEstate::where('code', 'GARDEN-CITY')->firstOrFail();

        $this->assertSame('admin', $admin->role->slug);
        $this->assertSame($estate->id, $admin->housing_estate_id);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_registration_rejects_duplicate_estate_code(): void
    {
        Livewire::test(Register::class)
            ->set('estateCode', 'HH-01')
            ->set('estateName', 'Duplikat')
            ->set('name', 'Budi')
            ->set('email', 'budi@duplikat.test')
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'rahasia123')
            ->call('register')
            ->assertHasErrors('estateCode');

        $this->assertDatabaseMissing('users', ['email' => 'budi@duplikat.test']);
    }

    public function test_new_estate_admin_cannot_see_existing_estate_data(): void
    {
        Livewire::test(Register::class)
            ->set('estateCode', 'garden-city')
            ->set('estateName', 'Garden City Residence')
            ->set('name', 'Budi Santoso')
            ->set('email', 'budi@garden.test')
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'rahasia123')
            ->call('register');

        // Admin baru otomatis masuk, dan hanya melihat housing-nya sendiri.
        $this->assertSame(0, House::count());
        $this->assertNull(Resident::where('name', 'Warga A-1')->first());
        $this->assertSame(1, HousingEstate::count());
    }

    public function test_super_admin_sees_all_estates(): void
    {
        [, , , $wargaB] = $this->makeEstateB();

        $super = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->firstOrFail();
        $super->forceFill(['housing_estate_id' => null])->save();

        $this->actingAs($super);

        $this->assertSame(2, HousingEstate::count());
        $this->assertNotNull(House::where('house_number', 'B-01')->first());
        $this->assertNotNull(User::find($wargaB->id));
    }

    public function test_staff_notification_does_not_leak_to_other_estate(): void
    {
        $this->makeEstateB();

        $stafB = $this->staffB('manage-complaint');
        $adminA = $this->estateAdminA();
        $wargaA = $this->residentA();

        // Staf estate A yang memegang permission yang sama.
        $stafA = $this->makeStaff('staf.a@housinghub.test', $this->roleWithPermission('manage-complaint'), $this->estateA()->id);

        $this->actingAs($wargaA);

        Livewire::actingAs($wargaA)
            ->test(ResidentComplaintsIndex::class)
            ->set('category', 'facility')
            ->set('title', 'Lampu mati')
            ->set('description', 'Lampu jalan rt. 3 mati')
            ->call('submit')
            ->assertHasNoErrors();

        // Hanya staf estate A yang diberi tahu, staf estate B tidak.
        $this->assertTrue($stafA->fresh()->unreadNotifications()->count() > 0
            || $stafA->notifications()->count() > 0);
        $this->assertSame(0, $stafB->fresh()->notifications()->count());

        // Staf estate B tetap tidak bisa melihat pengaduan estate A.
        $this->actingAs($stafB);
        $this->assertSame(0, Complaint::count());

        $this->actingAs($adminA);
        $this->assertSame(1, Complaint::count());
    }

    /**
     * IDOR: Menebak ID milik estate lain lalu membukanya langsung lewat URL
     * harus gagal. Global scope membuat route binding tidak menemukan
     * record, sehingga jawabannya 404 — bukan 200 dengan data orang lain.
     */
    public function test_direct_url_to_another_estates_record_returns_404(): void
    {
        [$estateB, $houseB, $residentB, $wargaB] = $this->makeEstateB();

        $complaintB = Complaint::create([
            'housing_estate_id' => $estateB->id,
            'resident_id' => $residentB->id,
            'ticket_number' => 'CPL-B-001',
            'category' => 'facility',
            'title' => 'Pengaduan estate B',
            'description' => 'Hanya untuk warga estate B',
            'status' => 'new',
        ]);

        $postB = Post::create([
            'housing_estate_id' => $estateB->id,
            'user_id' => $wargaB->id,
            'resident_id' => $residentB->id,
            'title' => 'Post rahasia estate B',
            'body' => 'Jangan sampai terbaca estate lain',
        ]);

        // Warga estate A tidak boleh membuka halaman milik estate B.
        $wargaA = $this->residentA();
        $this->actingAs($wargaA)
            ->get(route('resident.complaints.show', $complaintB->id))
            ->assertNotFound();
        $this->actingAs($wargaA)
            ->get(route('resident.forum.show', $postB->id))
            ->assertNotFound();

        // Admin estate A tidak boleh membuka form edit rumah / warga estate B.
        $adminA = $this->estateAdminA();
        $this->actingAs($adminA)
            ->get(route('admin.houses.edit', $houseB->id))
            ->assertNotFound();
        $this->actingAs($adminA)
            ->get(route('admin.residents.edit', $residentB->id))
            ->assertNotFound();

        // Sebaliknya, warga estate B tetap bisa membuka datanya sendiri.
        $this->actingAs($wargaB)
            ->get(route('resident.complaints.show', $complaintB->id))
            ->assertOk();
        $this->actingAs($wargaB)
            ->get(route('resident.forum.show', $postB->id))
            ->assertOk();
    }

    /**
     * IDOR pada data keuangan: tagihan dan bukti bayar milik estate lain
     * harus tidak bisa dibuka — baik oleh admin maupun warga estate sendiri
     * yang bukan pemiliknya.
     */
    public function test_billing_and_payment_derive_estate_when_caller_omits_it(): void
    {
        [, $houseB, $residentB, $wargaB] = $this->makeEstateB();

        // Kedua admin disiapkan sebelum ada actingAs: estateA() membaca
        // HousingEstate yang ter-filter global scope, jadi tidak bisa dipanggil
        // lagi setelah sesi berpindah ke estate lain.
        $adminB = $this->estateAdminB();
        $adminA = $this->estateAdminA();

        // Dibuat tanpa housing_estate_id: model harus menurunkannya sendiri dari
        // house, supaya baris tidak pernah hilang dari daftar tagihan admin.
        $billing = Billing::create([
            'invoice_number' => 'INV-B-DERIVE-0001',
            'house_id' => $houseB->id,
            'resident_id' => $residentB->id,
            'billing_type' => 'ipl',
            'period_month' => 2,
            'period_year' => 2026,
            'amount' => 250000,
            'total' => 250000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY/20260202/INV-B-DERIVE-0001-01',
            'billing_id' => $billing->id,
            'resident_id' => $residentB->id,
            'user_id' => $wargaB->id,
            'amount' => 250000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'pending',
        ]);

        $this->assertSame(
            $houseB->housing_estate_id,
            $billing->fresh()->housing_estate_id,
            'Tagihan harus mewarisi estate dari rumah.',
        );
        $this->assertSame(
            $houseB->housing_estate_id,
            $payment->fresh()->housing_estate_id,
            'Pembayaran harus mewarisi estate dari tagihan.',
        );

        // Admin estate B tetap melihatnya; estate A tetap nol.
        $this->actingAs($adminB);
        $this->assertSame(1, Billing::whereKey($billing->id)->count());
        $this->assertSame(1, Payment::whereKey($payment->id)->count());

        $this->actingAs($adminA);
        $this->assertSame(0, Billing::whereKey($billing->id)->count());
        $this->assertSame(0, Payment::whereKey($payment->id)->count());
    }

    /**
     * Halaman yang menampilkan field "Perumahan".
     *
     * @return array<int, string>
     */
    protected function estateFieldPages(): array
    {
        return [
            'admin.blocks.index',
            'admin.cash.accounts.index',
            'admin.ipl.rates.index',
            'admin.ipl.generate',
            'admin.water.rates.index',
            'admin.water.readings',
        ];
    }

    public function test_estate_admin_sees_no_useless_estate_dropdown(): void
    {
        $this->makeEstateB();
        $adminA = $this->estateAdminA();
        $estateName = $this->estateA()->name;

        foreach ($this->estateFieldPages() as $page) {
            $content = $this->actingAs($adminA)->get(route($page))
                ->assertOk()
                ->getContent();

            foreach (['wire:model="housing_estate_id"', 'wire:model.live="housing_estate_id"'] as $markup) {
                $this->assertStringNotContainsString(
                    $markup,
                    $content,
                    "Halaman {$page} masih menampilkan dropdown perumahan untuk admin estate.",
                );
            }

            // Field-nya diganti label statis, jadi nama housing-nya harus terlihat.
            $this->assertStringContainsString(
                $estateName,
                strip_tags($content),
                "Halaman {$page} tidak menampilkan nama perumahan sebagai label statis.",
            );
        }
    }

    public function test_platform_admin_keeps_the_estate_dropdown(): void
    {
        $this->makeEstateB();

        $platform = User::create([
            'name' => 'Platform Operator',
            'email' => 'platform@housinghub.test',
            'password' => 'password',
            'role_id' => $this->roleId('super_admin'),
            'status' => 'active',
        ]);

        foreach ($this->estateFieldPages() as $page) {
            $content = $this->actingAs($platform)->get(route($page))
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/wire:model(\.live)?="housing_estate_id"/',
                $content,
                "Halaman {$page} seharusnya tetap menyediakan dropdown perumahan untuk platform.",
            );
        }
    }

    public function test_resident_list_shows_the_estate_of_each_resident(): void
    {
        $estateA = $this->estateA();
        $adminA = $this->estateAdminA();

        $content = strip_tags($this->actingAs($adminA)->get(route('admin.residents.index'))
            ->assertOk()
            ->getContent());

        // Nama perumahan tempat warga tinggal ikut tampil untuk admin estate.
        $this->assertStringContainsString(
            $estateA->name,
            $content,
            'Daftar warga tidak menampilkan nama perumahan asal warga.',
        );
    }

    public function test_resident_list_does_not_leak_another_estate(): void
    {
        [$estateB, , $residentB] = $this->makeEstateB();
        $adminA = $this->estateAdminA();

        $html = $this->actingAs($adminA)->get(route('admin.residents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($estateB->name, $html);
        $this->assertStringNotContainsString($residentB->name, $html);
    }

    public function test_house_list_shows_the_estate_of_each_house(): void
    {
        $estateA = $this->estateA();
        $adminA = $this->estateAdminA();

        $content = strip_tags($this->actingAs($adminA)->get(route('admin.houses.index'))
            ->assertOk()
            ->getContent());

        // Nama perumahan asal rumah ikut tampil untuk admin estate.
        $this->assertStringContainsString(
            $estateA->name,
            $content,
            'Daftar rumah tidak menampilkan nama perumahan asal rumah.',
        );
    }

    public function test_house_list_does_not_leak_another_estate(): void
    {
        [$estateB, $houseB] = $this->makeEstateB();
        $adminA = $this->estateAdminA();

        $html = $this->actingAs($adminA)->get(route('admin.houses.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($estateB->name, $html);
        $this->assertStringNotContainsString($houseB->house_number, $html);
    }

    public function test_resident_of_one_estate_cannot_query_residents_of_another(): void
    {
        $this->makeEstateB();
        $wargaB = $this->findUserUnscoped('warga.b@housinghub.test');

        $this->actingAs($this->residentA());

        // Model Resident memakai scope lewat hunian, jadi query mentah pun
        // tidak boleh menemukan warga dari perumahan lain.
        $this->assertNull(Resident::find($wargaB->resident_id));
        $this->assertNull(Resident::where('name', 'Warga Estate B')->first());

        // Global scope User juga menutup akun warga lain.
        $this->assertNull(User::find($wargaB->id));
        $this->assertSame(
            0,
            User::whereNotNull('resident_id')->where('id', $wargaB->id)->count(),
        );
    }

    public function test_resident_cannot_open_another_estates_resident_pages(): void
    {
        [$estateB, $houseB, $residentB, $wargaB] = $this->makeEstateB();

        // housing_estate_id diisi eksplisit karena baris dibuat tanpa sesi
        // login. Kalau dibiarkan NULL, scope akan memperlakukannya sebagai
        // "data global" yang memang terlihat lintas condominan.
        $complaintB = Complaint::create([
            'housing_estate_id' => $estateB->id,
            'resident_id' => $residentB->id,
            'user_id' => $wargaB->id,
            'house_id' => $houseB->id,
            'category' => 'facility',
            'title' => 'Lampu mati di estate B',
            'description' => 'Laporan menit komplain estate B',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $postB = Post::create([
            'housing_estate_id' => $estateB->id,
            'resident_id' => $residentB->id,
            'user_id' => $wargaB->id,
            'title' => 'Postingan rahasia estate B',
            'body' => 'Isi rahasia dari perumahan lain',
            'category' => 'umum',
        ]);

        $wargaA = $this->residentA();

        // Halaman milik warga lain harus memblokir akses. Global scope membuat
        // route binding tidak menemukan record, jadi jawabannya 404 — bukan
        // 200 dengan data orang lain. Yang diuji di sini adalah sifat "tidak
        // boleh bisa diakses", bukan kode status spesifik.
        foreach ([route('resident.complaints.show', $complaintB->id), route('resident.forum.show', $postB->id)] as $url) {
            $response = $this->actingAs($wargaA)->get($url);

            $this->assertContains(
                $response->getStatusCode(),
                [403, 404],
                "Warga A seharusnya tidak bisa membuka {$url}, tapi dapat {$response->getStatusCode()}.",
            );
        }

        // Isi rahasianya tidak boleh bocor di halaman respons mana pun.
        foreach (['resident.complaints.index', 'resident.forum.index'] as $route) {
            $this->actingAs($wargaA)
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('Lampu mati di estate B')
                ->assertDontSee('Postingan rahasia estate B');
        }

        // Halaman daftar milik warga A sendiri tetap normal.
        $this->actingAs($wargaA)->get(route('resident.complaints.index'))->assertOk();
        $this->actingAs($wargaA)->get(route('resident.forum.index'))->assertOk();
    }

    public function test_resident_sees_only_own_estate_in_shared_pages(): void
    {
        $this->makeEstateB();

        $this->actingAs($this->residentA());

        // Halaman bersama (dashboard, profil, kas) tidak boleh memuat nama
        // maupun nomor telepon warga dari perumahan lain.
        foreach (['resident.dashboard', 'resident.profile', 'resident.cash.index'] as $route) {
            $html = $this->actingAs($this->residentA())
                ->get(route($route))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString(
                'Warga Estate B',
                $html,
                "Halaman {$route} membocorkan nama warga dari perumahan lain.",
            );
            $this->assertStringNotContainsString(
                'warga.b@housinghub.test',
                $html,
                "Halaman {$route} membocorkan email warga dari perumahan lain.",
            );
        }
    }

    public function test_resident_cannot_read_another_estates_billings(): void
    {
        [$estateB, $houseB, $residentB, $wargaB] = $this->makeEstateB();

        $billingB = Billing::create([
            'invoice_number' => 'IPL-B-RAHASIA-0001',
            'house_id' => $houseB->id,
            'resident_id' => $residentB->id,
            'billing_type' => 'ipl',
            'period_month' => 3,
            'period_year' => 2026,
            'amount' => 175000,
            'total' => 175000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ]);

        $this->actingAs($this->residentA());

        // Tagihan estate lain tidak boleh masuk daftar tagihan warga A,
        // dan halaman detailnya tidak boleh terbuka.
        $this->actingAs($this->residentA())
            ->get(route('resident.ipl.index'))
            ->assertOk()
            ->assertDontSee('IPL-B-RAHASIA-0001');

        $response = $this->actingAs($this->residentA())
            ->get(route('resident.ipl.show', $billingB->id));

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'Warga A seharusnya tidak bisa membuka tagihan estate lain.',
        );
    }

    public function test_guest_can_open_the_public_info_page(): void
    {
        $this->get(route('info'))
            ->assertOk()
            ->assertSee('Kelola seluruh perumahan', false)
            ->assertSee('Daftar Sekarang', false)
            // Halaman promosi, bukan dokumen teknis.
            ->assertDontSee('php artisan', false);
    }

    public function test_login_page_links_to_the_info_route(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('info'), false)
            ->assertDontSee('info.html', false);
    }

    public function test_only_super_admin_can_open_platform_dashboard(): void
    {
        $this->makeEstateB();
        $platform = $this->platformSuperAdmin();

        $this->actingAs($platform)->get(route('platform.dashboard'))->assertOk();

        // Admin condominan biasa tidak boleh melihat angka lintas condominan.
        $this->actingAs($this->estateAdminA())
            ->get(route('platform.dashboard'))
            ->assertForbidden();

        // Warga juga tidak.
        $this->actingAs($this->residentA())
            ->get(route('platform.dashboard'))
            ->assertForbidden();
    }

    public function test_platform_dashboard_summarises_every_estate(): void
    {
        [, $houseB, $residentB] = $this->makeEstateB();
        $estateA = $this->estateA();

        // Satu tagihan untuk estate A, satu lagi untuk estate B.
        foreach ([[$estateA->id, 'PLAT-A-0001', 100000], [null, 'PLAT-B-0001', 200000]] as [$estateId, $invoice, $amount]) {
            $house = $estateId === null ? $houseB : $estateA->houses()->first();

            Billing::create([
                'invoice_number' => $invoice,
                'house_id' => $house->id,
                'resident_id' => $residentB->id,
                'billing_type' => 'ipl',
                'period_month' => 1,
                'period_year' => 2026,
                'amount' => $amount,
                'total' => $amount,
                'due_date' => now()->addMonth()->toDateString(),
                'status' => 'unpaid',
            ]);
        }

        $this->actingAs($this->platformSuperAdmin())
            ->get(route('platform.dashboard'))
            ->assertOk()
            // Nama kedua condominan ikut tampil, jadi agregatnya lintas hook.
            ->assertSee($estateA->name)
            ->assertSee($houseB->block->estate->name);
    }

    public function test_platform_menu_only_appears_for_super_admin(): void
    {
        $this->actingAs($this->platformSuperAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('platform.dashboard'), false);

        $this->actingAs($this->estateAdminA())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('platform.dashboard'), false);
    }

    public function test_billing_and_payment_proof_are_not_reachable_across_estates(): void
    {
        [, $houseB, $residentB, $wargaB] = $this->makeEstateB();

        // Dibuat sebelum ada actingAs: hook tulis memaksa housing_estate_id
        // ke estate dari sesi aktif, jadi tidak boleh dibuat di tengah test.
        $wargaLainB = $this->makeResidentOfEstateB('warga.lain.b@housinghub.test', '3273010102000003');
        $adminA = $this->estateAdminA();

        $billingB = Billing::create([
            'invoice_number' => 'INV-B-0001',
            'house_id' => $houseB->id,
            'resident_id' => $residentB->id,
            'billing_type' => 'ipl',
            'period_month' => 1,
            'period_year' => 2026,
            'amount' => 150000,
            'total' => 150000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ]);

        $paymentB = Payment::create([
            'payment_number' => 'PAY/20260101/INV-B-0001-01',
            'billing_id' => $billingB->id,
            'resident_id' => $residentB->id,
            'user_id' => $wargaB->id,
            'amount' => 150000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'proof_blob' => 'GIF89a-bukti-b',
            'proof_mime' => 'image/gif',
            'proof_name' => 'bukti-b.gif',
            'status' => 'pending',
        ]);

        // Admin estate A tidak boleh membuka tagihan estate B.
        $this->actingAs($adminA)
            ->get(route('admin.ipl.billings.show', $billingB->id))
            ->assertNotFound();

        // Warga estate A tidak boleh membuka bukti bayar estate B.
        $wargaA = $this->residentA();
        $this->actingAs($wargaA)
            ->get(route('payments.proof', $paymentB->id))
            ->assertNotFound();

        // Warga estate lain (bukan A, tapi juga bukan pemilik tagihan ini)
        // tetap tidak boleh melihat bukti bayar tersebut.
        $this->actingAs($wargaLainB)
            ->get(route('payments.proof', $paymentB->id))
            ->assertForbidden();

        // Pemilik sah (warga B) tetap bisa melihat bukti bayarnya sendiri.
        $this->actingAs($wargaB)
            ->get(route('payments.proof', $paymentB->id))
            ->assertOk();
    }
}
