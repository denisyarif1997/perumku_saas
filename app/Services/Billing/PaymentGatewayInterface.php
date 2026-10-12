<?php

namespace App\Services\Billing;

use App\Models\EstateSubscriptionInvoice;

/** Kontrak gateway pembayaran langganan (Midtrans / Xendit / Manual). */
interface PaymentGatewayInterface
{
    public function name(): string;

    /** Buat sesi bayar, kembalikan URL redirect + referensi gateway. */
    public function createPayment(EstateSubscriptionInvoice $invoice): array;

    /** Verifikasi signature webhook. */
    public function verifyWebhook(array $payload, array $headers = []): bool;

    /** Petakan status gateway → paid|pending|failed|expired. */
    public function mapStatus(array $payload): string;

    /** Ambil nomor invoice internal dari payload webhook. */
    public function invoiceNumber(array $payload): ?string;
}
