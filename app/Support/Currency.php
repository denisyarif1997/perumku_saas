<?php

namespace App\Support;

class Currency
{
    /**
     * Format angka menjadi rupiah tanpa desimal, contoh: Rp 150.000
     */
    public static function rupiah(float|int|string|null $value, bool $withPrefix = true): string
    {
        $formatted = number_format((float) ($value ?? 0), 0, ',', '.');

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }

    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? '-';
    }

    /**
     * Label periode, contoh: September 2026
     */
    public static function period(int $year, int $month): string
    {
        return self::monthName($month).' '.$year;
    }

    public const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
}
