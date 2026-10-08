<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu kunjungan pengguna ke sebuah halaman.
 *
 * Sengaja TIDAK memakai trait BelongsToEstate: jejak kunjungan bersifat global
 * (termasuk tamu yang belum login), bukan data milik satu condominan. Yang
 * disimpan hanya housing_estate_id hasil resolusi dari user yang sedang
 * masuk — dipakai dashboard platform untuk membandingkan pemakaian antar
 * condominan, bukan untuk menyaring akses.
 *
 * Aturan pencatatan (lihat middleware TrackPageVisit):
 * - hanya GET, bukan permintaan Livewire (XHR) maupun prefetch navigasi;
 * - satu kunjungan per pengguna (atau IP untuk tamu) per halaman per jendela
 *   DEDUPE_WINDOW_MINUTES, sehingga refresh atau wire:navigate tidak membuat
 *   jumlah kunjungan membludak.
 */
class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    /** Jeda minimal sebelum kunjungan ke halaman yang sama dihitung lagi. */
    public const DEDUPE_WINDOW_MINUTES = 30;

    protected $fillable = ['user_id', 'housing_estate_id', 'path', 'ip_address', 'user_agent'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    /**
     * Batasi ke kunjungan sejak satu momen (dipakai dashboard platform).
     */
    public function scopeSince(Builder $query, DateTimeInterface|string $moment): Builder
    {
        return $query->where('created_at', '>=', $moment);
    }

    /**
     * Apakah kunjungan serupa sudah tercatat di dalam jendela dedupe.
     *
     * Yang sudah login dicocokkan lewat user_id, sedangkan tamu dicocokkan
     * lewat kombinasi user_id NULL + IP, sehingga dua orang berbeda tidak
     * bisa terhitung sebagai satu pengunjung.
     */
    public static function isDuplicateOfRecentVisit(string $path, ?int $userId, ?string $ip): bool
    {
        return static::query()
            ->where('path', $path)
            ->since(now()->subMinutes(self::DEDUPE_WINDOW_MINUTES))
            ->when(
                $userId !== null,
                fn (Builder $query) => $query->where('user_id', $userId),
                fn (Builder $query) => $query->whereNull('user_id')->where('ip_address', $ip),
            )
            ->exists();
    }

    /**
     * Kelompok perangkat dari user agent, untuk dashboard platform.
     *
     * Pencocokan string sederhana memang dipakai karena proyek ini tidak
     * membawa paket deteksi perangkat, dan yang dibutuhkan di sini hanya
     * pengelompokan kasar: ponsel, tablet, atau desktop.
     */
    public function deviceLabel(): string
    {
        $agent = strtolower((string) $this->user_agent);

        if ($agent === '') {
            return 'Tidak diketahui';
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet') || str_contains($agent, 'silk')) {
            return 'Tablet';
        }

        // Tablet Android tidak menyebut "Mobile" di user agent-nya, jadi
        // harus diperiksa sebelum cabang ponsel.
        if (str_contains($agent, 'android') && ! str_contains($agent, 'mobile')) {
            return 'Tablet';
        }

        if (str_contains($agent, 'mobi') || str_contains($agent, 'android') || str_contains($agent, 'iphone')) {
            return 'Ponsel';
        }

        return 'Desktop';
    }
}
