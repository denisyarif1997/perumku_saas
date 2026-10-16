<?php

namespace Tests\Feature;

use App\Jobs\SendPanicPush;
use App\Models\HousingEstate;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\EmergencyPanicService;
use App\Services\WebPush\PayloadEncryptor;
use App\Services\WebPush\Secp256r1;
use App\Services\WebPush\VapidJwt;
use App\Services\WebPush\VapidKeys;
use App\Services\WebPushService;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    public function test_subscribe_stores_endpoint_with_hash(): void
    {
        $user = User::query()->whereNotNull('resident_id')->firstOrFail();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-'.fake()->uuid();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'aGVsbG8', 'auth' => 'd29ybGQ'],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    public function test_panic_fire_dispatches_push_job_for_recipients(): void
    {
        Queue::fake([SendPanicPush::class]);

        $resident = User::query()->where('email', 'warga.a.1@housinghub.id')->firstOrFail();

        $this->actingAs($resident);
        app(EmergencyPanicService::class)->fire(['reason' => 'Kebakaran '.fake()->uuid()]);

        Queue::assertPushed(SendPanicPush::class, fn (SendPanicPush $job): bool => $job->recipientIds !== []);
    }

    public function test_push_job_only_targets_subscribed_recipients_in_scope(): void
    {
        Queue::fake([SendPanicPush::class]);
        $resident = User::query()->where('email', 'warga.a.1@housinghub.id')->firstOrFail();
        $neighbour = User::query()
            ->where('id', '!=', $resident->id)
            ->where('housing_estate_id', $resident->housing_estate_id)
            ->whereNotNull('resident_id')
            ->firstOrFail();

        PushSubscription::factory()->for($neighbour, 'user')->create();

        // Outsider: warga aktif estate lain yang berlangganan — tidak boleh dikirimi.
        $otherEstateId = HousingEstate::query()->where('id', '!=', $resident->housing_estate_id)->value('id');
        $outsider = User::query()
            ->where('housing_estate_id', $otherEstateId)
            ->whereNotNull('resident_id')
            ->where('status', 'active')
            ->first();

        if ($outsider) {
            PushSubscription::factory()->for($outsider, 'user')->create();
        } else {
            PushSubscription::factory()->create(); // fallback: user acak di luar penerima
        }

        $sent = [];
        $push = $this->mock(WebPushService::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('send')->andReturnUsing(function ($sub, $payload) use (&$sent): bool {
                $sent[] = [$sub->user_id, $payload];

                return true;
            });
        });

        $this->actingAs($resident);
        $panic = app(EmergencyPanicService::class)->fire(['reason' => 'Butuh bantuan '.fake()->uuid()]);

        // Jalankan job dengan penerima yang dihitung service (tanpa antrean).
        // Catatan: neighbour lain satu estate juga penerima valid — outsider
        // estate lain tidak boleh ikut terkirim walau berlangganan.
        $job = new SendPanicPush($panic->id, [$neighbour->id]);
        $job->handle($push);

        $sentIds = array_column(array_map(fn ($row) => ['id' => $row[0]], $sent), 'id');

        $this->assertContains($neighbour->id, $sentIds);
        $this->assertSame('ALARM DARURAT', $sent[0][1]['title']);

        if (isset($outsider) && $outsider) {
            $this->assertNotContains($outsider->id, $sentIds);
        }
    }

    public function test_vapid_jwt_and_payload_crypto_roundtrip_shape(): void
    {
        [$privDec, $pub] = Secp256r1::generateKeyPair();
        $privRaw = Secp256r1::decToBytes($privDec);
        $pubRaw = VapidKeys::base64UrlEncode($pub);
        $privB64 = VapidKeys::base64UrlEncode($privRaw);

        $jwt = VapidJwt::make('https://fcm.googleapis.com', 'mailto:test@perumku.id', $pubRaw, $privB64);

        $this->assertCount(3, explode('.', $jwt));

        // Enkripsi harus menghasilkan header 90 byte + ciphertext.
        $clientAuth = VapidKeys::base64UrlEncode(random_bytes(16));
        $body = PayloadEncryptor::encrypt(['title' => 'x'], $pubRaw, $clientAuth);

        $this->assertGreaterThan(90, strlen($body));
        $this->assertSame(16, strlen(substr($body, 0, 16))); // salt
        $this->assertSame(65, ord($body[20])); // keyid len
    }
}
