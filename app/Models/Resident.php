<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use BelongsToEstate, SoftDeletes;

    /**
     * Warga hanya terlihat bila punya hunian di estate aktif. Warga yang
     * belum terikat ke rumah mana pun tidak ditampilkan ke admin estate.
     */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        if ($estateId === CurrentEstate::NONE) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('houseResidents.house', fn (Builder $q) => $q->where('housing_estate_id', $estateId));
    }

    protected $fillable = [
        'nik', 'name', 'gender', 'birth_date', 'phone', 'email', 'photo', 'status',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function houseResidents(): HasMany
    {
        return $this->hasMany(HouseResident::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function primaryHouse(): ?House
    {
        $pivot = $this->houseResidents()->where('is_primary', true)->where('status', 'active')->first();

        return $pivot?->house;
    }

    /**
     * Nama perumahan tempat warga tinggal.
     *
     * Diambil dari hunian pertama yang sudah dimuat, sehingga pemanggil
     * wajib eager-load `houseResidents.house.estate` (lihat halaman
     * daftar warga) supaya tidak terjadi query per baris.
     */
    public function estateName(): ?string
    {
        return $this->houseResidents->first()?->house?->estate?->name;
    }
}
