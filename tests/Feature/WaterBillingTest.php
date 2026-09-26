<?php

namespace Tests\Feature;

use App\Livewire\Admin\Water\Rates\Index as WaterRateIndex;
use App\Livewire\Admin\Water\Readings as WaterReadings;
use App\Models\Billing;
use App\Models\House;
use App\Models\User;
use App\Models\WaterMeterReading;
use App\Models\WaterRate;
use App\Services\WaterBillingService;
use App\Support\ImageCompressor;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Livewire;
use Tests\TestCase;

class WaterBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    public function test_generate_water_billing_from_meter_reading(): void
    {
        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air 2026',
            'price_per_m3' => 5000, 'admin_fee' => 10000, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);

        $house = House::where('status', 'active')->firstOrFail();
        $svc = app(WaterBillingService::class);
        $out = $svc->recordAndGenerate(2026, 9, null, 10, User::firstOrFail()->id, $rate->id, [$house->id => 12]);

        $this->assertSame(1, $out['created']);
        $billing = Billing::where('billing_type', 'water')->firstOrFail();
        $this->assertSame('AIR/202609/'.str_pad((string) $house->id, 4, '0', STR_PAD_LEFT), $billing->invoice_number);
        $this->assertEquals(12, (float) $billing->usage_m3);
        $this->assertEquals(12 * 5000 + 10000, (float) $billing->total);

        $again = $svc->recordAndGenerate(2026, 9, null, 10, 1, $rate->id, [$house->id => 20]);
        $this->assertSame(1, $again['skipped']);
        $this->assertSame(1, Billing::where('billing_type', 'water')->count());
    }

    public function test_reject_backward_meter(): void
    {
        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air 2026',
            'price_per_m3' => 5000, 'admin_fee' => 0, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);

        $house = House::where('status', 'active')->firstOrFail();
        $svc = app(WaterBillingService::class);
        $out = $svc->recordAndGenerate(2026, 8, null, 10, User::firstOrFail()->id, $rate->id, [$house->id => 10]);
        $out = $svc->recordAndGenerate(2026, 9, null, 10, 1, $rate->id, [$house->id => 5]);

        $this->assertSame(1, $out['invalid']);
        $this->assertSame(0, Billing::where('billing_type', 'water')->where('period_month', 9)->count());
    }

    public function test_draft_readings_saved_before_generating_billing(): void
    {
        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air 2026',
            'price_per_m3' => 5000, 'admin_fee' => 10000, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);

        $house = House::where('status', 'active')->firstOrFail();
        $svc = app(WaterBillingService::class);
        $userId = User::firstOrFail()->id;

        // Simpan bacaan dulu (draft) — belum ada tagihan.
        $saved = $svc->saveReadings(2026, 9, null, $userId, $rate->id, [$house->id => 12]);
        $this->assertSame(1, $saved['saved']);
        $this->assertSame(0, Billing::where('billing_type', 'water')->count());

        $reading = WaterMeterReading::where('house_id', $house->id)
            ->where('period_year', 2026)->where('period_month', 9)->firstOrFail();
        $this->assertSame('draft', $reading->status);

        // Generate tagihan dari draft.
        $gen = $svc->generateFromDrafts(2026, 9, null, 10, $userId, $rate->id);
        $this->assertSame(1, $gen['created']);

        $billing = Billing::where('billing_type', 'water')->firstOrFail();
        $this->assertEquals(12 * 5000 + 10000, (float) $billing->total);
        $this->assertSame('billed', $reading->fresh()->status);
        $this->assertSame($billing->id, $reading->fresh()->billing_id);
    }

    public function test_admin_can_clear_selected_photo_before_saving(): void
    {
        Storage::fake('public');

        $admin = User::where('email', 'admin@housinghub.id')->firstOrFail();
        $house = House::where('status', 'active')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(WaterReadings::class)
            ->set('photos.'.$house->id, UploadedFile::fake()->image('meter.jpg', 900, 600))
            ->call('clearSelectedPhoto', $house->id)
            ->assertSet('photos.'.$house->id, null);
    }

    public function test_meter_photo_compressed_and_stored_on_storage(): void
    {
        Storage::fake('public');

        // Buat JPEG ber-noise yang ukurannya > 1MB.
        $img = imagecreatetruecolor(1600, 1200);
        mt_srand(42);
        $colors = [];
        for ($i = 0; $i < 256; $i++) {
            $colors[] = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        }
        for ($x = 0; $x < 1600; $x++) {
            for ($y = 0; $y < 1200; $y++) {
                imagesetpixel($img, $x, $y, $colors[mt_rand(0, 255)]);
            }
        }
        ob_start();
        imagejpeg($img, null, 98);
        $raw = (string) ob_get_clean();
        imagedestroy($img);
        $this->assertGreaterThan(1024 * 1024, strlen($raw));

        $compressed = ImageCompressor::compressToJpeg($raw);
        $this->assertNotNull($compressed);
        $this->assertSame('image/jpeg', $compressed['mime']);
        $this->assertLessThanOrEqual(1024 * 1024, strlen($compressed['data']));

        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air Foto',
            'price_per_m3' => 5000, 'admin_fee' => 0, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);
        $house = House::where('status', 'active')->firstOrFail();
        $svc = app(WaterBillingService::class);

        $path = 'water-meters/test-house.jpg';
        Storage::disk('public')->put($path, $compressed['data']);

        $svc->saveReadings(2026, 9, null, User::firstOrFail()->id, $rate->id, [$house->id => 12], [$house->id => $path]);

        $reading = WaterMeterReading::where('house_id', $house->id)->firstOrFail();
        $this->assertSame($path, $reading->photo_path);
        Storage::disk('public')->assertExists($path);
        $this->assertLessThanOrEqual(1024 * 1024, strlen((string) Storage::disk('public')->get($path)));
        $this->assertStringContainsString('/storage/water-meters/test-house.jpg', (string) $reading->photoUrl());
    }

    public function test_readings_page_accepts_photo_upload_and_saves_draft(): void
    {
        Storage::fake('public');

        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air 2026',
            'price_per_m3' => 5000, 'admin_fee' => 0, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);
        $house = House::where('status', 'active')->firstOrFail();
        $admin = User::where('email', 'admin@housinghub.id')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(WaterReadings::class)
            ->set('meters.'.$house->id, 15)
            ->set('photos.'.$house->id, UploadedFile::fake()->image('meter.jpg', 900, 600))
            ->call('save')
            ->assertHasNoErrors()
            // Foto tersimpan + draft harus tampil di halaman (panel foto & info draft).
            ->assertSee('Draft tersimpan')
            ->assertSee('Foto meteran tersimpan')
            ->assertSee('/storage/water-meters/');

        $reading = WaterMeterReading::where('house_id', $house->id)->firstOrFail();
        $this->assertSame('draft', $reading->status);
        $this->assertNotNull($reading->photo_path);
        Storage::disk('public')->assertExists($reading->photo_path);

        // Draft terakhir harus tampil kembali di input meter akhir (pre-fill).
        Livewire::actingAs($admin)
            ->test(WaterReadings::class)
            ->assertSet('meters.'.$house->id, '15.00')
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertSame(1, Billing::where('billing_type', 'water')->count());
        $this->assertSame('billed', $reading->fresh()->status);
    }

    public function test_browser_upload_endpoint_saves_photo_file(): void
    {
        // Meniru alur browser asli: POST file ke endpoint Livewire lalu _finishUpload.
        Storage::fake('public');

        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air Browser',
            'price_per_m3' => 5000, 'admin_fee' => 0, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);
        $house = House::where('status', 'active')->firstOrFail();
        $admin = User::where('email', 'admin@housinghub.id')->firstOrFail();

        // Disk Livewire khusus test agar endpoint upload berjalan seperti di browser.
        config(['filesystems.disks.tmp-for-tests' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/livewire-tmp'),
        ]]);

        $signedUrl = app(GenerateSignedUploadUrl::class)->forLocal();

        $response = $this->actingAs($admin)->post($signedUrl, [
            'files' => [UploadedFile::fake()->image('meter.jpg', 900, 600)],
        ]);
        $response->assertOk();
        $signedPath = $response->json('paths.0');
        $this->assertNotEmpty($signedPath);

        Livewire::actingAs($admin)
            ->test(WaterReadings::class)
            ->set('meters.'.$house->id, 15)
            ->call('_finishUpload', 'photos.'.$house->id, [$signedPath], false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('/storage/water-meters/');

        $reading = WaterMeterReading::where('house_id', $house->id)->firstOrFail();
        $this->assertNotNull($reading->photo_path);
        Storage::disk('public')->assertExists($reading->photo_path);
    }

    public function test_photo_can_attach_to_existing_draft_without_changing_meter(): void
    {
        Storage::fake('public');

        $rate = WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air 2026',
            'price_per_m3' => 5000, 'admin_fee' => 0, 'min_usage_m3' => 0,
            'effective_date' => now()->startOfYear()->toDateString(), 'status' => 'active',
        ]);
        $house = House::where('status', 'active')->firstOrFail();
        $other = House::where('status', 'active')->where('id', '!=', $house->id)->firstOrFail();
        $svc = app(WaterBillingService::class);
        $userId = User::firstOrFail()->id;

        $svc->saveReadings(2026, 9, null, $userId, $rate->id, [$house->id => 12]);

        // Foto dipilih tanpa mengisi ulang angka meter -> dilampirkan ke draft.
        Storage::disk('public')->put('water-meters/attach-1.jpg', 'JPGDATA');
        $res = $svc->saveReadings(2026, 9, null, $userId, $rate->id, [], [
            $house->id => 'water-meters/attach-1.jpg',
        ]);
        $this->assertSame(1, $res['attached']);

        $reading = WaterMeterReading::where('house_id', $house->id)->firstOrFail();
        $this->assertSame('water-meters/attach-1.jpg', $reading->photo_path);
        $this->assertEquals(12, (float) $reading->meter_end);

        // Rumah tanpa bacaan draft -> foto ditolak dan dilaporkan.
        Storage::disk('public')->put('water-meters/attach-2.jpg', 'JPGDATA2');
        $res2 = $svc->saveReadings(2026, 9, null, $userId, $rate->id, [], [
            $other->id => 'water-meters/attach-2.jpg',
        ]);
        $this->assertSame(1, $res2['photo_dropped']);
        $this->assertSame(0, $res2['attached']);
    }

    public function test_water_rates_page_lists_existing_rates(): void
    {
        WaterRate::create([
            'housing_estate_id' => null, 'name' => 'Air Khusus 2026',
            'price_per_m3' => 4500, 'admin_fee' => 0, 'min_usage_m3' => 0,
            'effective_date' => now()->toDateString(), 'status' => 'active',
        ]);

        Livewire::actingAs(User::where('email', 'admin@housinghub.id')->firstOrFail())
            ->test(WaterRateIndex::class)
            ->assertSee('Air Khusus 2026')
            ->assertSee('Aktif')
            ->assertSee('Nama Tarif');
    }
}
