<?php

namespace App\Services\WebPush;

/**
 * ECDSA P-256 RFC 6979 (deterministik) PHP-murni via BCMath.
 */
class EcdsaP256
{
    public static function sign(string $privDec, string $hash32): string
    {
        $n = Secp256r1::N;
        $e = Secp256r1::bytesToDec($hash32);
        $privBytes = Secp256r1::decToBytes($privDec);

        $k = self::rfc6979($privBytes, $hash32);

        if (bccomp($k, '0') <= 0 || bccomp($k, $n) >= 0) {
            throw new \RuntimeException('Nonce ECDSA invalid.');
        }

        [$rx] = Secp256r1::mul(Secp256r1::decToBytes($k), Secp256r1::GX, Secp256r1::GY);
        $r = Secp256r1::modFix($rx, $n);

        if (bccomp($r, '0') === 0) {
            throw new \RuntimeException('ECDSA r nol.');
        }

        $s = Secp256r1::modFix(bcmul(Secp256r1::inv($k, $n), Secp256r1::modFix(bcadd($e, bcmul($privDec, $r)), $n)), $n);

        if (bccomp($s, '0') === 0) {
            throw new \RuntimeException('ECDSA s nol.');
        }

        // Low-S (BIP62) agar lolos verifikasi ketat.
        $half = bcdiv($n, '2', 0);
        if (bccomp($s, $half) > 0) {
            $s = bcsub($n, $s);
        }

        return Secp256r1::decToBytes($r).Secp256r1::decToBytes($s);
    }

    protected static function rfc6979(string $privBytes, string $hash): string
    {
        $n = Secp256r1::N;
        $qlen = 32;

        $v = str_repeat("\x01", 32);
        $k = str_repeat("\x00", 32);

        $k = hash_hmac('sha256', $v."\x00".$privBytes.$hash, $k, true);
        $v = hash_hmac('sha256', $v, $k, true);
        $k = hash_hmac('sha256', $v."\x01".$privBytes.$hash, $k, true);
        $v = hash_hmac('sha256', $v, $k, true);

        for ($i = 0; $i < 1000; $i++) {
            $v = hash_hmac('sha256', $v, $k, true);
            $t = $v;

            while (strlen($t) < $qlen) {
                $v = hash_hmac('sha256', $v, $k, true);
                $t .= $v;
            }

            $cand = Secp256r1::bytesToDec(substr($t, 0, $qlen));

            if (bccomp($cand, '0') > 0 && bccomp($cand, $n) < 0) {
                return $cand;
            }

            $k = hash_hmac('sha256', $v."\x00", $k, true);
            $v = hash_hmac('sha256', $v, $k, true);
        }

        throw new \RuntimeException('RFC6979 gagal.');
    }
}
