<?php

namespace App\Services;

use App\Models\EstateRegistration;
use App\Models\OAuthAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Enkripsi ulang token Google lama yang masih plaintext.
 *
 * Dijalankan sekali via migrasi. Baris yang gagal dekripsi + gagal
 * di-crypt ulang dilewati (token dibiarkan, tidak dihapus).
 */
class OAuthTokenReencryptor
{
    public function run(): int
    {
        $fixed = 0;

        OAuthAccount::query()->where('provider', 'google')->chunkById(200, function ($accounts) use (&$fixed) {
            foreach ($accounts as $account) {
                $dirty = false;
                $attributes = $account->getAttributes();

                foreach (['access_token', 'refresh_token'] as $column) {
                    $raw = $attributes[$column] ?? null;

                    if (! is_string($raw) || $raw === '') {
                        continue;
                    }

                    if ($this->isEncrypted($raw)) {
                        continue;
                    }

                    $account->setAttribute($column, $raw);
                    $dirty = true;
                }

                if ($dirty) {
                    $account->save();
                    $fixed++;
                }
            }
        });

        return $fixed;
    }

    protected function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    public static function legacyRefreshToken(EstateRegistration|OAuthAccount $model): ?string
    {
        $raw = $model instanceof OAuthAccount
            ? $model->getAttributes()['refresh_token'] ?? null
            : DB::table('oauth_accounts')->whereKey($model->bridge_id)->value('refresh_token');

        if (! is_string($raw) || $raw === '') {
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
