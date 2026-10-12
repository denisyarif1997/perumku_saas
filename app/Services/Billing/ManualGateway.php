<?php

namespace App\Services\Billing;

use App\Models\EstateSubscriptionInvoice;

/** Mode tanpa gateway online: superadmin verifikasi pembayaran manual. */
class ManualGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function createPayment(EstateSubscriptionInvoice $invoice): array
    {
        return ['payment_url' => null, 'reference' => 'manual-'.$invoice->number];
    }

    public function verifyWebhook(array $payload, array $headers = []): bool
    {
        return false;
    }

    public function mapStatus(array $payload): string
    {
        return 'pending';
    }

    public function invoiceNumber(array $payload): ?string
    {
        return isset($payload['number']) ? (string) $payload['number'] : null;
    }
}
