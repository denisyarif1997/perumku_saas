<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected $fillable = [
        'housing_estate_id', 'resident_id', 'user_id',
        'title', 'body', 'category', 'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class)->orderBy('created_at');
    }

    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('created_at');
    }

    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }

    /**
     * Nama penulis posting: pakai akun user, fallback ke data warga.
     */
    public function authorName(): string
    {
        return $this->author?->name ?? $this->resident?->name ?? 'Warga';
    }

    /**
     * Rumah penulis posting, contoh: "A-1".
     */
    public function authorHouseLabel(): ?string
    {
        $resident = $this->resident;

        if (! $resident) {
            return null;
        }

        if ($resident->relationLoaded('houseResidents')) {
            $houseResident = $resident->houseResidents->firstWhere('is_primary', true)
                ?? $resident->houseResidents->first();

            return $houseResident?->house?->fullLabel();
        }

        return $resident->primaryHouse()?->fullLabel();
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'pengumuman' => 'Pengumuman',
            'tanya' => 'Tanya Jawab',
            'jual-beli' => 'Jual Beli',
            'fasilitas' => 'Fasilitas',
            'keamanan' => 'Keamanan',
            'kegiatan' => 'Kegiatan',
            default => 'Umum',
        };
    }

    public function categoryColor(): string
    {
        return match ($this->category) {
            'pengumuman' => 'sky',
            'tanya' => 'amber',
            'jual-beli' => 'green',
            'fasilitas' => 'purple',
            'keamanan' => 'red',
            'kegiatan' => 'pink',
            default => 'slate',
        };
    }

    /**
     * Daftar kategori yang tersedia untuk form.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'umum' => 'Umum',
            'pengumuman' => 'Pengumuman',
            'tanya' => 'Tanya Jawab',
            'jual-beli' => 'Jual Beli',
            'fasilitas' => 'Fasilitas',
            'keamanan' => 'Keamanan',
            'kegiatan' => 'Kegiatan',
        ];
    }
}
