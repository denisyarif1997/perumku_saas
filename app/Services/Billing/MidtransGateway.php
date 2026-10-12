<?php

namespace App\Services\Billing;

use App\Models\EstateSubscriptionInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway Midtrans via Snap API (tanpa SDK tambahan, memakai HTTP client Laravel).
 *
 * Membutuhkan: MIDTRANS_SERVER_KEY (+ CLIENT_KEY untuk Snap.js bila dipakai),
 * MIDTRANS_IS_PRODUCTION=false untuk sandbox.
 */
class MidtransGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'midtrans';
    }

    protected function baseUrl(): string
    {
        return config('subscription.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    public function createPayment(EstateSubscriptionInvoice $invoice): array
    {
        $serverKey = (string) config('subscription.midtrans.server_key');

        if ($serverKey === '') {
            return ['payment_url' => null, 'reference' => null, 'error' => 'MIDTRANS_SERVER_KEY belum diisi.'];
        }

        $invoice->loadMissing('estate');

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()->asJson()
                ->post($this->baseUrl().'/transactions', [
                    'transaction_details' => [
                        'order_id' => $invoice->number,
                        'gross_amount' => (int) $invoice->total_amount,
                    ],
                    'item_details' => [[
                        'id' => $invoice->type,
                        'price' => (int) $invoice->total_amount,
                        'quantity' => 1,
                        'name' => 'Langganan Perumku '.$invoice->period_label,
                    ]],
                    'customer_details' => [
                        'first_name' => $invoice->estate?->name ?? 'Perumahan',
                        'email' => $invoice->estate?->email,
                        'phone' => $invoice->estate?->phone,
                    ],
                    'callbacks' => [
                        'finish' => route('subscription.finish', ['invoice' => $invoice->number]),
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Midtrans create payment gagal', ['invoice' => $invoice->number, 'body' => $response->body()]);

                return ['payment_url' => null, 'reference' => null, 'error' => 'Midtrans menolak permintaan bayar.'];
            }

            $token = $response->json('token');
            $url = $response->json('redirect_url');

            return ['payment_url' => $url, 'reference' => (string) $token];
        } catch (\Throwable $e) {
            Log::error('Midtrans exception', ['invoice' => $invoice->number, 'error' => $e->getMessage()]);

            return ['payment_url' => null, 'reference' => null, 'error' => 'Tidak dapat menghubungi Midtrans.'];
        }
    }

    public function verifyWebhook(array $payload, array $headers = []): bool
    {
        $serverKey = (string) config('subscription.midtrans.server_key');

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey);

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    public function mapStatus(array $payload): string
    {
        $tx = $payload['transaction_status'] ?? '';
        $fraud = $payload['fraud_status'] ?? '';

        if ($tx === 'capture' && $fraud !== 'challenge' || $tx === 'settlement') {
            return 'paid';
        }

        if (in_array($tx, ['pending'], true)) {
            return 'pending';
        }

        if (in_array($tx, ['expire', 'cancel'], true)) {
            return 'expired';
        }

        return 'failed';
    }

    public function invoiceNumber(array $payload): ?string
    {
        return isset($payload['order_id']) ? (string) $payload['order_id'] : null;
    }
}
