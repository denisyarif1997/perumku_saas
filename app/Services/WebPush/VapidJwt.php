<?php

namespace App\Services\WebPush;

/**
 * JWT VAPID (ES256): tanda tangan via ECDSA PHP-murni agar tidak
 * tergantung openssl_pkey_new yang gagal di sebagian build Windows.
 */
class VapidJwt
{
    public static function make(string $audience, string $subject, string $publicKeyRaw, string $privateKeyRaw): string
    {
        $publicBytes = VapidKeys::base64UrlDecode($publicKeyRaw);

        if (strlen($publicBytes) !== 65 || $publicBytes[0] !== "\x04") {
            throw new \RuntimeException('VAPID_PUBLIC_KEY harus 65 byte uncompressed base64url.');
        }

        $privateBytes = VapidKeys::base64UrlDecode($privateKeyRaw);

        if (strlen($privateBytes) !== 32) {
            if (str_contains($privateKeyRaw, 'PRIVATE KEY')) {
                $privateBytes = VapidKeys::privateRawFromPem($privateKeyRaw);
            } else {
                throw new \RuntimeException('VAPID_PRIVATE_KEY harus 32 byte base64url.');
            }
        }

        $header = VapidKeys::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = VapidKeys::base64UrlEncode((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $subject,
        ]));

        $data = $header.'.'.$payload;

        // Coba OpenSSL dulu (PEM SEC1), fallback ke ECDSA murni.
        try {
            $pem = VapidKeys::privatePem($privateBytes, $publicBytes);
            $key = openssl_pkey_get_private($pem);

            if ($key !== false) {
                $der = '';

                if (openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256)) {
                    return $data.'.'.VapidKeys::base64UrlEncode(self::derToRaw($der));
                }
            }
        } catch (\Throwable) {
            // fallback di bawah
        }

        $sig = EcdsaP256::sign(Secp256r1::bytesToDec($privateBytes), hash('sha256', $data, true));

        return $data.'.'.VapidKeys::base64UrlEncode($sig);
    }

    protected static function derToRaw(string $der): string
    {
        $pos = 4;
        $rLen = ord($der[$pos - 1]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen + 2;
        $sLen = ord($der[$pos - 1]);
        $s = substr($der, $pos, $sLen);

        return str_pad(substr($r, -32), 32, "\x00", STR_PAD_LEFT).str_pad(substr($s, -32), 32, "\x00", STR_PAD_LEFT);
    }
}
