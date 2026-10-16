<?php

namespace App\Console\Commands;

use App\Services\WebPush\Secp256r1;
use App\Services\WebPush\VapidKeys;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('push:vapid {--force : Timpa kunci yang sudah ada}')]
#[Description('Buat VAPID keys untuk Web Push (disimpan ke .env).')]
class EnsurePushVapidKeys extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $existingPublic = (string) config('services.webpush.public_key');
        $existingPrivate = (string) config('services.webpush.private_key');

        if ($existingPublic && $existingPrivate && ! $this->option('force')) {
            $this->info('VAPID keys sudah ada. Gunakan --force untuk membuat ulang.');

            return self::SUCCESS;
        }

        // PHP-murni via BCMath (tidak tergantung openssl_pkey_new EC
        // yang gagal di sebagian build Windows/Laragon).
        [$privDec, $publicUncompressed] = Secp256r1::generateKeyPair();

        $this->writeEnv('VAPID_PUBLIC_KEY', VapidKeys::base64UrlEncode($publicUncompressed));
        $this->writeEnv('VAPID_PRIVATE_KEY', VapidKeys::base64UrlEncode(Secp256r1::decToBytes($privDec)));

        if (! env('VAPID_SUBJECT')) {
            $this->writeEnv('VAPID_SUBJECT', 'mailto:admin@perumku.id');
        }

        $this->info('VAPID keys dibuat dan disimpan ke .env.');

        return self::SUCCESS;
    }

    protected function writeEnv(string $key, string $value): void
    {
        $path = base_path('.env');
        $contents = file_exists($path) ? (string) file_get_contents($path) : '';
        $line = $key.'='.$value;

        if (preg_match('/^'.$key.'=.*/m', $contents)) {
            $contents = (string) preg_replace('/^'.$key.'=.*/m', $line, $contents);
        } else {
            $contents = rtrim($contents)."\n".$line."\n";
        }

        file_put_contents($path, $contents);
    }
}
