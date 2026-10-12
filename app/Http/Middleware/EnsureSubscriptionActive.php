<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use App\Support\CurrentEstate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci area admin/resident bila langganan estate habis (trial lewat
 * atau periode berbayar lewat + grace). Superadmin platform lolos.
 * Halaman langganan + webhook selalu lolos agar masih bisa membayar.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('super_admin')) {
            return $next($request);
        }

        if ($request->routeIs('subscription.*', 'subscription-webhook')) {
            return $next($request);
        }

        $estateId = CurrentEstate::id();

        if ($estateId === null || $estateId === CurrentEstate::NONE) {
            return $next($request);
        }

        $service = app(SubscriptionService::class);
        $subscription = $service->forEstate($estateId);

        if ($subscription) {
            $subscription = $service->refreshStatus($subscription);
        }

        if (! $service->isEstateOperational($estateId)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Masa trial/langganan perumahan telah berakhir.'], 402);
            }

            return redirect()->route('subscription.index')
                ->with('error', 'Masa trial/langganan telah berakhir. Selesaikan pembayaran untuk membuka kembali akses.');
        }

        return $next($request);
    }
}
