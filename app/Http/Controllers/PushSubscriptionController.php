<?php

namespace App\Http\Controllers;

use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function vapidKey(WebPushService $push): JsonResponse
    {
        abort_unless($push->isConfigured(), 503, 'Web Push belum dikonfigurasi.');

        return response()->json(['publicKey' => $push->publicKey()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $request->user()->pushSubscriptions()
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
