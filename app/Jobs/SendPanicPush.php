<?php

namespace App\Jobs;

use App\Models\EmergencyPanic;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPanicPush implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $panicId, public array $recipientIds = [])
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(WebPushService $push): void
    {
        if (! $push->isConfigured() || $this->recipientIds === []) {
            return;
        }

        $panic = EmergencyPanic::query()->find($this->panicId);

        if (! $panic) {
            return;
        }

        $payload = [
            'title' => 'ALARM DARURAT',
            'body' => ($panic->user?->name ? $panic->user->name.': ' : '').($panic->reason ?: 'Ada warga membutuhkan bantuan segera.'),
            'url' => route('resident.panic'),
            'panic_id' => $panic->id,
            'tag' => 'panic-'.$panic->id,
        ];

        PushSubscription::query()
            ->whereIn('user_id', $this->recipientIds)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($push, $payload): void {
                foreach ($subscriptions as $subscription) {
                    $push->send($subscription, $payload);
                }
            });
    }
}
