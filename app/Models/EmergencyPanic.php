<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyPanic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'resident_id',
        'reason',
        'latitude',
        'longitude',
        'status',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'meta' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at')->limit(20);
    }

    public function scopeOpenByUser($query, int $userId)
    {
        return $query->open()->where('user_id', $userId);
    }

    public function scopeOpenForEstate($query, int $estateId)
    {
        return $query->open()->whereHas('resident', function ($query) use ($estateId) {
            $query->whereHas('houseResidents', function ($query) use ($estateId) {
                $query->where('status', 'active')->whereHas('house', function ($query) use ($estateId) {
                    $query->where('housing_estate_id', $estateId);
                });
            });
        });
    }

    /**
     * Koordinat tampil sebagai boolean & string terformat untuk view/API.
     */
    public function getHasCoordinatesAttribute(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getFormattedLatitudeAttribute(): ?string
    {
        return $this->latitude !== null ? number_format((float) $this->latitude, 7, '.', '') : null;
    }

    public function getFormattedLongitudeAttribute(): ?string
    {
        return $this->longitude !== null ? number_format((float) $this->longitude, 7, '.', '') : null;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        return array_merge($array, [
            'hasCoordinates' => $this->hasCoordinates,
            'formattedLatitude' => $this->formattedLatitude,
            'formattedLongitude' => $this->formattedLongitude,
        ]);
    }
}
