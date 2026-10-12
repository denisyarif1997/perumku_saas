<?php

namespace App\Services\Billing;

use App\Models\EstateSubscriptionInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway Xendit via Invoice API (tanpa SDK tambahan).
 * Membutuhkan XENDIT_SECRET_KEY dan XENDIT_CALLBACK_TOKEN untuk webhook.
 */
class XenditGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'xendit';
    }

    public function createPayment(EstateSubscriptionInvoice $invoice): array
    {
        $secret = (string) config('subscription.xendit.secret_key');

        if ($secret === '') {
            return ['payment_url' => null, 'reference' => null, 'error' => 'XENDIT_SECRET_KEY belum diisi.'];
        }

        $invoice->loadMissing('estate');

        try {
            $response = Http::withBasicAuth($secret, '')
                ->acceptJson()->asJson()
                ->post('https://api.xendit.co/v2/invoices', [
                    'external_id' => $invoice->number,
                    'amount' => (int) $invoice->total_amount,
                    'description' => 'Langganan Perumku '.$invoice->period_label,
                    'payer_email' => $invoice->estate?->email,
                    'success_redirect_url' => config('subscription.xendit.success_url')
                        ?? route('subscription.finish', ['invoice' => $invoice->number]),
                ]);

            if (! $response->successful()) {
                Log::warning('Xendit create invoice gagal', ['invoice' => $invoice->number, 'body' => $response->body()]);

                return ['payment_url' => null, 'reference' => null, 'error' => 'Xendit menolak permintaan bayar.'];
            }

            return [
                'payment_url' => $response->json('invoice_url'),
                'reference' => (string) $response->json('id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Xendit exception', ['invoice' => $invoice->number, 'error' => $e->getMessage()]);

            return ['payment_url' => null, 'reference' => null, 'error' => 'Tidak dapat menghubungi Xendit.'];
        }
    }

    public function verifyWebhook(array $payload, array $headers = []): bool
    {
        $token = (string) config('subscription.xendit.callback_token');

        if ($token === '') {
            return false;
        }

        $given = $headers['x-callback-token'] ?? $headers['X-Callback-Token'] ?? null;

        return $given !== null && hash_equals($token, (string) $given);
    }

    public function mapStatus(array $payload): string
    {
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if ($status === 'PAID' || $status === 'SETTLED') {
            return 'paid';
        }

        if ($status === 'PENDING') {
            return 'pending';
        }

        if ($status === 'EXPIRED') {
            return 'expired';
        }

        return 'failed';
    }

    public function invoiceNumber(array $payload): ?string
    {
        return isset($payload['external_id']) ? (string) $payload['external_id'] : null;
    }
}
