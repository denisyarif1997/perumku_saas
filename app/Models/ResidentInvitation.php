<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * Undangan warga tanpa SMTP: admin membuat link bertoken, lalu membagikannya
 * via WhatsApp / salin link. Token mentah hanya tampil sekali saat dibuat,
 * di database tersimpan sebagai hash.
 */
class ResidentInvitation extends Model
{
    protected $fillable = [
        'housing_estate_id', 'resident_id', 'house_id', 'invited_by',
        'name', 'phone', 'email', 'token_hash', 'status',
        'expires_at', 'accepted_at', 'accepted_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->status === 'pending'
            && now()->greaterThan($this->expires_at);
    }

    /** Cari undangan pending dari token URL (verifikasi hash). */
    public static function findByToken(string $token): ?self
    {
        $candidates = static::query()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->limit(50)
            ->get();

        foreach ($candidates as $invitation) {
            if (Hash::check($token, $invitation->token_hash)) {
                return $invitation;
            }
        }

        return null;
    }
}
