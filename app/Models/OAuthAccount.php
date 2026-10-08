<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class OAuthAccount extends Model
{
    protected $table = 'oauth_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'name',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ambil refresh_token apa adanya: coba dekripsi, gagal → anggap plaintext
     * lama. Mencegah DecryptException saat membaca baris yang dibuat sebelum
     * cast 'encrypted' dipasang.
     */
    public function rawRefreshToken(): ?string
    {
        $raw = $this->getAttributes()['refresh_token'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($raw);

            return $decrypted === '' ? null : $decrypted;
        } catch (DecryptException) {
            return $raw;
        }
    }
}
