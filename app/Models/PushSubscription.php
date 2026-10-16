<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'user_agent',
    ];

    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription): void {
            $subscription->endpoint_hash = hash('sha256', (string) $subscription->endpoint);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
