<?php

namespace App\Http\Controllers;

use App\Models\EstateSubscriptionInvoice;
use App\Services\Billing\GatewayFactory;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Webhook pembayaran langganan (Midtrans & Xendit, tanpa auth). */
class SubscriptionWebhookController extends Controller
{
    public function handle(Request $request, string $gateway, SubscriptionService $service): Response
    {
        $driver = GatewayFactory::make($gateway);
        $payload = $request->all();
        $headers = collect($request->headers->all())->mapWithKeys(fn ($v, $k) => [$k => $v[0] ?? null])->all();

        if (! $driver->verifyWebhook($payload, $headers)) {
            Log::warning('Subscription webhook signature invalid', ['gateway' => $gateway]);

            return response('Invalid signature', 403);
        }

        $number = $driver->invoiceNumber($payload);

        if (! $number) {
            return response('Unknown invoice', 404);
        }

        $invoice = EstateSubscriptionInvoice::query()->where('number', $number)->first();

        if (! $invoice) {
            return response('Invoice not found', 404);
        }

        $status = $driver->mapStatus($payload);

        if ($status === 'paid') {
            $service->markPaid($invoice, $payload);
        } elseif (in_array($status, ['failed', 'expired'], true)) {
            $invoice->forceFill([
                'status' => $status,
                'gateway_payload' => json_encode($payload),
            ])->save();
        }

        return response('OK', 200);
    }
}
