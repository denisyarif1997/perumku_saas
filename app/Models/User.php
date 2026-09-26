<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\CurrentEstate;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToEstate, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'resident_id', 'housing_estate_id', 'role_id', 'name', 'email', 'password',
        'phone', 'avatar', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Berbeda dari model lain, akun TIDAK memakai aturan "atau IS NULL" saat
     * sudah terikat estate: admin estate tidak boleh melihat akun platform
     * (housing_estate_id null) maupun akun dari estate lain.
     *
     * Untuk akun yang belum terikat ke perumahan mana pun (NONE), hanya baris
     * global yang terlihat, sehingga akun tersebut tidak bisa menyentuh
     * data tenant mana pun.
     */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        if ($estateId === CurrentEstate::NONE) {
            $query->whereNull('housing_estate_id');

            return;
        }

        $query->where('housing_estate_id', $estateId);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs, true);
    }

    /**
     * Cek apakah user memiliki salah satu permission yang diberikan.
     * Role super_admin selalu dianggap memiliki semua permission.
     */
    public function hasPermission(string ...$slugs): bool
    {
        if (! $this->role) {
            return false;
        }
        if ($this->role->slug === 'super_admin') {
            return true;
        }

        return $this->role->hasAnyPermission($slugs);
    }

    /**
     * Cek apakah user memiliki seluruh permission yang diberikan.
     */
    public function hasAllPermissions(string ...$slugs): bool
    {
        if (! $this->role) {
            return false;
        }
        if ($this->role->slug === 'super_admin') {
            return true;
        }

        $granted = $this->role->permissionSlugs();

        foreach ($slugs as $slug) {
            if (! in_array($slug, $granted, true)) {
                return false;
            }
        }

        return true;
    }

    public function isResident(): bool
    {
        return $this->hasRole('resident');
    }

    /**
     * User aktif yang berperan staf dengan permission tertentu.
     * Role super_admin dianggap memiliki semua permission.
     */
    public static function staffWithPermission(string $permission): Builder
    {
        return static::query()
            ->where('status', 'active')
            ->where(function (Builder $query) use ($permission) {
                $query->whereHas('role', fn (Builder $role) => $role->where('slug', 'super_admin'))
                    ->orWhereHas('role.permissions', fn (Builder $perm) => $perm->where('slug', $permission));
            });
    }

    /**
     * Seluruh akun user aktif yang terhubung dengan data warga tertentu.
     */
    public static function residentUsers(?int $residentId): Builder
    {
        return static::query()
            ->where('resident_id', $residentId)
            ->where('status', 'active');
    }

    /**
     * Akun warga aktif pada estate tertentu (melalui rumah hunian aktifnya).
     * Jika estate null → seluruh akun warga aktif (berlaku untuk pengumuman global).
     */
    public static function residentUsersOfEstate(?int $estateId): Builder
    {
        $query = static::query()
            ->where('status', 'active')
            ->whereNotNull('resident_id');

        if ($estateId === null) {
            return $query;
        }

        return $query->whereHas('resident.houseResidents', fn (Builder $pivot) => $pivot
            ->where('status', 'active')
            ->whereHas('house', fn (Builder $house) => $house->where('housing_estate_id', $estateId)));
    }
}
