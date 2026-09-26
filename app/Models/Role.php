<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }

    /**
     * Cek apakah role memiliki salah satu dari permission yang diberikan.
     *
     * @param  array<int, string>  $slugs
     */
    public function hasAnyPermission(array $slugs): bool
    {
        if ($slugs === []) {
            return false;
        }

        return array_intersect($slugs, $this->permissionSlugs()) !== [];
    }

    /**
     * Daftar slug permission milik role ini (memakai relasi yang sudah dimuat bila ada).
     *
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        return $this->permissions->pluck('slug')->all();
    }
}
