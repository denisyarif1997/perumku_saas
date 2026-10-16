<?php

namespace App\Services\WebPush;

/**
 * ECDH secp256r1: pakai openssl derive bila bisa, fallback BCMath murni.
 */
class EcdhSecp256r1
{
    public static function agree(string $localPrivatePemOrDec, string $peerUncompressed): string
    {
        // Coba jalur OpenSSL dulu (cepat).
        try {
            $localPem = str_contains($localPrivatePemOrDec, 'PRIVATE KEY')
                ? $localPrivatePemOrDec
                : VapidKeys::privatePem(Secp256r1::decToBytes($localPrivatePemOrDec), Secp256r1::publicFromPrivate($localPrivatePemOrDec));

            $local = openssl_pkey_get_private($localPem);
            $peer = openssl_pkey_get_public(self::peerPem($peerUncompressed));

            if ($local !== false && $peer !== false) {
                $secret = openssl_pkey_derive($peer, $local, 32);

                if ($secret !== false && strlen($secret) === 32) {
                    return $secret;
                }
            }
        } catch (\Throwable) {
            // lanjut ke fallback BCMath
        }

        $privDec = str_contains($localPrivatePemOrDec, 'PRIVATE KEY')
            ? Secp256r1::bytesToDec(VapidKeys::privateRawFromPem($localPrivatePemOrDec))
            : $localPrivatePemOrDec;

        $peerX = Secp256r1::bytesToDec(substr($peerUncompressed, 1, 32));
        $peerY = Secp256r1::bytesToDec(substr($peerUncompressed, 33, 32));

        return Secp256r1::agree($privDec, $peerX, $peerY);
    }

    public static function generateEphemeral(): array
    {
        // Jalur OpenSSL dulu.
        try {
            $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

            if ($key !== false) {
                $pem = '';
                openssl_pkey_export($key, $pem);
                $privRaw = VapidKeys::privateRawFromPem($pem);
                $details = openssl_pkey_get_details($key);
                $pub = "\x04"
                    .hex2bin(str_pad($details['ec']['x'] ?? '', 64, '0', STR_PAD_LEFT))
                    .hex2bin(str_pad($details['ec']['y'] ?? '', 64, '0', STR_PAD_LEFT));

                return [$pem, $pub];
            }
        } catch (\Throwable) {
            // fallback di bawah
        }

        [$privDec, $pub] = Secp256r1::generateKeyPair();

        return [$privDec, $pub];
    }

    public static function peerPem(string $uncompressed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(hex2bin(
                '3059301306072a8648ce3d020106082a8648ce3d030107034200'.bin2hex($uncompressed)
            )), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }
}
