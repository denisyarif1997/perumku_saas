<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Penentu estate (perumahan) aktif untuk konteks saat ini.
 *
 * Nilai hasil id():
 *  - null  → tanpa filter (platform): super_admin, staf tanpa estate, tamu, CLI/seeder.
 *  - int X → terbatas estate X: baris housing_estate_id = X ATAU IS NULL (data global).
 *  - -1    → terbatas baris global saja (warga terikat tapi tanpa rumah hunian).
 *
 * Resolusi dibakukan lazy dari auth()->user() (bukan middleware) agar juga
 * berlaku pada request update Livewire dan pemanggilan Livewire::test().
 * Guard $resolving mencegah rekursi: EloquentUserProvider memakai newQuery()
 * (ikut global scope), sehingga query auth dapat memicu scope ini kembali.
 */
class CurrentEstate
{
    /** Penanda "terikat tanpa rumah" — hanya baris global (IS NULL) yang terlihat. */
    public const NONE = -1;

    protected static bool $resolving = false;

    protected static bool $resolved = false;

    protected static ?int $estateId = null;

    protected static ?int $userId = null;

    public static function id(): ?int
    {
        if (static::$resolving) {
            // Resolusi sedang berjalan (mis. query auth di dalamnya) → jangan saring.
            return null;
        }

        static::$resolving = true;

        try {
            $user = Auth::user();

            if ($user === null) {
                static::flush();

                return null;
            }

            if (static::$resolved && static::$userId === $user->id) {
                return static::$estateId;
            }

            $estateId = static::resolveFor($user);

            static::$userId = $user->id;
            static::$estateId = $estateId;
            static::$resolved = true;

            return $estateId;
        } finally {
            static::$resolving = false;
        }
    }

    /**
     * Estate yang boleh ditulis oleh pengguna saat ini, atau null bila platform
     * (memakai nilai dari input). Melempar 403 bila pengguna terikat tanpa rumah.
     */
    public static function writableId(): ?int
    {
        $id = static::id();

        if ($id === self::NONE) {
            abort(403, 'Akun Anda belum terikat ke perumahan mana pun.');
        }

        return $id;
    }

    public static function flush(): void
    {
        static::$resolved = false;
        static::$estateId = null;
        static::$userId = null;
    }

    protected static function resolveFor(User $user): ?int
    {
        // Platform super_admin selalu tanpa filter.
        if ($user->hasRole('super_admin')) {
            return null;
        }

        // Staf yang sudah terikat estate.
        if ($user->housing_estate_id !== null) {
            return (int) $user->housing_estate_id;
        }

        // Warga: estate dari rumah hunian aktif (query mentah → tanpa global scope).
        if ($user->resident_id !== null) {
            $estateId = DB::table('house_residents')
                ->join('houses', 'houses.id', '=', 'house_residents.house_id')
                ->where('house_residents.resident_id', $user->resident_id)
                ->where('house_residents.status', 'active')
                ->orderByDesc('house_residents.is_primary')
                ->value('houses.housing_estate_id');

            return $estateId !== null ? (int) $estateId : self::NONE;
        }

        // Staf non-platform tanpa estate → tidak boleh melihat data estate mana pun,
        // hanya baris global. Super_admin sudah tertangani di atas.
        return self::NONE;
    }
}
