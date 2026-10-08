<?php

use App\Services\OAuthTokenReencryptor;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Enkripsi ulang token Google lama (plaintext) agar cocok dengan cast
     * 'encrypted' di OAuthAccount. Idempotent: baris yang sudah terenkripsi
     * dilewati. Gagal-crypt tidak menggagalkan migrate.
     */
    public function up(): void
    {
        try {
            app(OAuthTokenReencryptor::class)->run();
        } catch (Throwable) {
            // Token lama tidak boleh menggagalkan deploy; callback Google
            // berikutnya akan menimpa dengan token terenkripsi baru.
        }
    }

    public function down(): void
    {
        // Tidak reversibel (dekripsi massal tidak diinginkan).
    }
};
