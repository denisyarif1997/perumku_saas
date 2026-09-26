<?php

namespace App\Support;

/**
 * Kompresi gambar meteran air menjadi JPEG <= batas tertentu (default 1MB)
 * untuk disimpan sebagai BLOB di database.
 */
class ImageCompressor
{
    public const MAX_BYTES = 1024 * 1024; // 1 MB

    /**
     * Kompres biner gambar menjadi JPEG dengan ukuran <= $maxBytes.
     *
     * @return array{data: string, mime: string}|null null jika bukan gambar valid
     */
    public static function compressToJpeg(string $binary, int $maxBytes = self::MAX_BYTES): ?array
    {
        if ($binary === '') {
            return null;
        }

        // JPEG yang sudah cukup kecil disimpan apa adanya (tanpa re-encode).
        $info = @getimagesizefromstring($binary);
        if ($info !== false && ($info[2] ?? null) === IMAGETYPE_JPEG && strlen($binary) <= $maxBytes) {
            return ['data' => $binary, 'mime' => 'image/jpeg'];
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, 2000 / max($w, $h, 1));
        $quality = 85;

        $data = null;
        for ($i = 0; $i < 14; $i++) {
            $tw = max(1, (int) round($w * $scale));
            $th = max(1, (int) round($h * $scale));

            $img = imagecreatetruecolor($tw, $th);
            $bg = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $bg);
            imagecopyresampled($img, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

            ob_start();
            imagejpeg($img, null, $quality);
            $data = (string) ob_get_clean();
            imagedestroy($img);

            if (strlen($data) <= $maxBytes) {
                break;
            }

            if ($quality > 45) {
                $quality -= 15;
            } elseif ($scale > 0.2) {
                $scale *= 0.8;
            } else {
                break; // best effort pada iterasi terakhir
            }
        }

        imagedestroy($src);

        return ['data' => (string) $data, 'mime' => 'image/jpeg'];
    }
}
