<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class House extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected $fillable = [
        'housing_estate_id', 'housing_block_id', 'house_number', 'address',
        'land_area', 'building_area', 'ownership_status', 'occupancy_status', 'status',
    ];

    protected function casts(): array
    {
        return ['land_area' => 'decimal:2', 'building_area' => 'decimal:2'];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(HousingBlock::class, 'housing_block_id');
    }

    public function houseResidents(): HasMany
    {
        return $this->hasMany(HouseResident::class);
    }

    public function primaryResident(): ?HouseResident
    {
        return $this->houseResidents()->where('is_primary', true)->where('status', 'active')->first();
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    public function outstandingBillings(): HasMany
    {
        return $this->hasMany(Billing::class)->whereIn('status', ['unpaid', 'partial']);
    }

    public function fullLabel(): string
    {
        return ($this->block ? $this->block->code.'-' : '').$this->house_number;
    }
}
