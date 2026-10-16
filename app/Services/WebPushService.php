<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Services\WebPush\PayloadEncryptor;
use App\Services\WebPush\VapidJwt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim Web Push (VAPID) tanpa dependency tambahan.
 */
class WebPushService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.webpush.public_key')
            && (bool) config('services.webpush.private_key');
    }

    public function publicKey(): ?string
    {
        $key = config('services.webpush.public_key');

        return $key ? (string) $key : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $parts = parse_url($subscription->endpoint);

            if (! isset($parts['scheme'], $parts['host'])) {
                $subscription->delete();

                return false;
            }

            $jwt = VapidJwt::make(
                $parts['scheme'].'://'.$parts['host'],
                (string) config('services.webpush.subject', 'mailto:admin@perumku.id'),
                (string) config('services.webpush.public_key'),
                (string) config('services.webpush.private_key'),
            );

            $body = PayloadEncryptor::encrypt($payload, $subscription->p256dh, $subscription->auth);

            $response = Http::withHeaders([
                'Authorization' => 'vapid t='.$jwt.', k='.config('services.webpush.public_key'),
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL' => '1800',
                'Urgency' => 'high',
            ])->withBody($body, 'application/octet-stream')->post($subscription->endpoint);

            if (in_array($response->status(), [404, 410], true)) {
                $subscription->delete();

                return false;
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[webpush] gagal kirim', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
