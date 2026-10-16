<?php

namespace App\Services\WebPush;

/**
 * Util base64url + bangun PEM EC dari raw VAPID.
 */
class VapidKeys
{
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        return (string) base64_decode($padded);
    }

    /**
     * Bangun PEM SEC1 dari private raw 32 byte + public 65 byte.
     */
    public static function privatePem(string $privateRaw, string $publicUncompressed): string
    {
        $der = hex2bin('30770201010420').$privateRaw.hex2bin('a00a06082a8648ce3d030107a144034200').$publicUncompressed;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    /**
     * Ambil 32 byte private scalar dari PEM SEC1/PKCS8.
     */
    public static function privateRawFromPem(string $pem): string
    {
        $der = base64_decode((string) preg_replace('/-----(BEGIN|END)[^-]+-----|\s/', '', $pem));
        $pos = strrpos($der, "\x04\x20");

        if ($pos === false) {
            throw new \RuntimeException('Private key EC tidak bisa dibaca.');
        }

        return substr($der, $pos + 2, 32);
    }
}
