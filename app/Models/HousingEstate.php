<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousingEstate extends Model
{
    use BelongsToEstate;

    protected $fillable = ['code', 'name', 'address', 'phone', 'email', 'logo', 'status'];

    /** Kolom identitas estate itu sendiri (bukan housing_estate_id). */
    public function estateScopeColumn(): string
    {
        return 'id';
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(HousingBlock::class);
    }

    public function houses(): HasMany
    {
        return $this->hasMany(House::class);
    }
}
