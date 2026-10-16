<?php

namespace App\Services\WebPush;

/**
 * Aritmetika secp256r1 (P-256) PHP-murni via BCMath.
 * Dipakai karena openssl_pkey_new EC gagal di sebagian build Windows.
 */
class Secp256r1
{
    public const P = '1157920892103562487626974469494075735300861434152903144290761824530314650329';

    public const N = '115792089210356248762697446949407573529996955224135760342422259061068512044369';

    public const GX = '484395612939064517590525852527979142027629495260417479958440807170824046352769';

    public const GY = '361342509567497957985851279195878819566224168260612454677140467032910705084548513940402393294538088907585952512624399322587839009462938552261292302143129';

    public static function generateKeyPair(): array
    {
        do {
            $priv = self::bytesToDec(random_bytes(32));
        } while (bccomp($priv, '0') <= 0 || bccomp($priv, self::N) >= 0);

        [$x, $y] = self::mul(self::decToBytes($priv), self::GX, self::GY);

        return [$priv, "\x04".self::decToBytes($x).self::decToBytes($y)];
    }

    public static function publicFromPrivate(string $privDec): string
    {
        [$x, $y] = self::mul(self::decToBytes($privDec), self::GX, self::GY);

        return "\x04".self::decToBytes($x).self::decToBytes($y);
    }

    public static function agree(string $privDec, string $peerXDec, string $peerYDec): string
    {
        [$x] = self::mul(self::decToBytes($privDec), $peerXDec, $peerYDec);

        return self::decToBytes($x);
    }

    /**
     * @return array{0: string, 1: string} koordinat desimal (x, y)
     */
    public static function mul(string $scalar32, string $xDec, string $yDec): array
    {
        $p = self::P;
        $a = bcsub($p, '3');

        $bin = '';
        foreach (str_split(bin2hex($scalar32)) as $c) {
            $bin .= str_pad(base_convert($c, 16, 2), 4, '0', STR_PAD_LEFT);
        }
        $bin = ltrim($bin, '0');

        $rx = null;
        $ry = null;
        $qx = $xDec;
        $qy = $yDec;

        foreach (str_split($bin === '' ? '0' : $bin) as $bit) {
            if ($rx !== null) {
                [$rx, $ry] = self::double($rx, $ry, $p, $a);
            }
            if ($bit === '1') {
                if ($rx === null) {
                    $rx = $qx;
                    $ry = $qy;
                } else {
                    [$rx, $ry] = self::add($rx, $ry, $qx, $qy, $p, $a);
                }
            }
            [$qx, $qy] = self::double($qx, $qy, $p, $a);
        }

        return [$rx ?? '0', $ry ?? '0'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function add(string $x1, string $y1, string $x2, string $y2, string $p, string $a): array
    {
        if ($x1 === $x2 && $y1 === $y2) {
            return self::double($x1, $y1, $p, $a);
        }

        $lam = bcmod(bcmul(self::modFix(bcsub($y2, $y1), $p), self::inv(self::modFix(bcsub($x2, $x1), $p), $p)), $p);
        $x3 = self::modFix(bcsub(bcsub(bcmul($lam, $lam), $x1), $x2), $p);
        $y3 = self::modFix(bcsub(bcmul($lam, self::modFix(bcsub($x1, $x3), $p)), $y1), $p);

        return [$x3, $y3];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function double(string $x, string $y, string $p, string $a): array
    {
        $lam = bcmod(bcmul(self::modFix(bcadd(bcmul('3', bcmul($x, $x)), $a), $p), self::inv(self::modFix(bcmul('2', $y), $p), $p)), $p);
        $x3 = self::modFix(bcsub(bcmul($lam, $lam), bcmul('2', $x)), $p);
        $y3 = self::modFix(bcsub(bcmul($lam, self::modFix(bcsub($x, $x3), $p)), $y), $p);

        return [$x3, $y3];
    }

    public static function inv(string $n, string $p): string
    {
        return bcpowmod(self::modFix($n, $p), bcsub($p, '2'), $p);
    }

    public static function modFix(string $n, string $p): string
    {
        $r = bcmod($n, $p);

        return $r !== '' && $r[0] === '-' ? bcadd($r, $p) : $r;
    }

    public static function bytesToDec(string $bytes): string
    {
        $dec = '0';
        foreach (str_split(bin2hex($bytes)) as $c) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($c));
        }

        return $dec;
    }

    public static function decToBytes(string $dec): string
    {
        $hex = '';
        $n = ltrim($dec, '0');
        $n = $n === '' ? '0' : $n;

        while (bccomp($n, '0') > 0) {
            $hex = dechex((int) bcmod($n, '16')).$hex;
            $n = bcdiv($n, '16', 0);
        }

        return hex2bin(str_pad($hex === '' ? '0' : $hex, 64, '0', STR_PAD_LEFT));
    }
}
