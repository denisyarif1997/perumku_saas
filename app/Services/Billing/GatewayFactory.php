<?php

namespace App\Services\Billing;

/** Pilih implementasi gateway sesuai config subscription.gateway. */
class GatewayFactory
{
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        return match (strtolower((string) ($gateway ?? config('subscription.gateway', 'midtrans')))) {
            'xendit' => new XenditGateway,
            'manual' => new ManualGateway,
            default => new MidtransGateway,
        };
    }
}
