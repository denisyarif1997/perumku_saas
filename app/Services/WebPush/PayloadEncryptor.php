<?php

namespace App\Services\WebPush;

/**
 * Enkripsi payload aes128gcm (RFC 8291 §3).
 */
class PayloadEncryptor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encrypt(array $payload, string $p256dh, string $auth): string
    {
        $plaintext = "\x02".((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $clientPublic = VapidKeys::base64UrlDecode($p256dh);
        $clientAuth = VapidKeys::base64UrlDecode($auth);

        if (strlen($clientPublic) !== 65 || strlen($clientAuth) !== 16) {
            throw new \RuntimeException('Subscription key tidak valid.');
        }

        [$ephemeralPriv, $serverPublic] = EcdhSecp256r1::generateEphemeral();

        $salt = random_bytes(16);
        $shared = EcdhSecp256r1::agree($ephemeralPriv, $clientPublic);

        $ikm = self::hkdf($clientAuth, $shared, "WebPush: info\x00".$clientPublic.$serverPublic, 32);
        $cek = self::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($cipher === false) {
            throw new \RuntimeException('Enkripsi Web Push gagal.');
        }

        return $salt.pack('N', 4096).chr(strlen($serverPublic)).$serverPublic.$cipher.$tag;
    }

    protected static function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $okm = '';
        $t = '';
        for ($i = 1; strlen($okm) < $length; $i++) {
            $t = hash_hmac('sha256', $t.$info.chr($i), $prk, true);
            $okm .= $t;
        }

        return substr($okm, 0, $length);
    }
}
