<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IplRate extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected $fillable = [
        'housing_estate_id', 'name', 'amount', 'period_type',
        'effective_date', 'end_date', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForEstate(Builder $query, ?int $estateId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('housing_estate_id', $estateId)
            ->orWhereNull('housing_estate_id'));
    }

    /**
     * Tarif yang berlaku pada tanggal tertentu (prioritas tarif per-perumahan).
     */
    public static function forDate(?int $estateId, mixed $date = null): ?self
    {
        $date = $date ?: now()->toDateString();

        return static::query()
            ->active()
            ->forEstate($estateId)
            ->where('effective_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->orderByRaw('housing_estate_id is null')
            ->orderByDesc('effective_date')
            ->first();
    }

    public function periodLabel(): string
    {
        return match ($this->period_type) {
            'quarterly' => 'Per 3 Bulan',
            'yearly' => 'Per Tahun',
            default => 'Per Bulan',
        };
    }

    public function label(): string
    {
        return $this->name.' — '.($this->estate?->name ?? 'Semua Perumahan');
    }
}
