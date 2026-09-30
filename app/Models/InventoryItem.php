<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Barang inventaris yang bisa dipinjam warga, mis. kursi, tenda, atau mixer.
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use BelongsToEstate, HasFactory, SoftDeletes;

    protected $fillable = [
        'housing_estate_id', 'name', 'code', 'description', 'quantity',
        'unit', 'location', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(ItemLoan::class);
    }

    /**
     * Pinjaman yang sedang berjalan: sudah diserahkan tapi belum dikembalikan.
     * Inilah yang mengurangi jumlah unit yang masih boleh dipinjam.
     */
    public function activeLoans(): HasMany
    {
        return $this->loans()->where('status', 'loaned');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%"));
    }

    /**
     * Jumlah unit yang sedang dipinjam oleh warga.
     */
    public function borrowedQuantity(): int
    {
        return $this->loans()->where('status', 'loaned')->count();
    }

    /**
     * Jumlah unit yang masih boleh dipinjam.
     */
    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->borrowedQuantity());
    }

    public function isLoanable(): bool
    {
        return $this->status === 'available' && $this->availableQuantity() > 0;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'maintenance' => 'Perawatan',
            'retired' => 'Tidak Dipakai',
            default => 'Tersedia',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'maintenance' => 'amber',
            'retired' => 'slate',
            default => 'green',
        };
    }

    /**
     * Status yang bisa dipilih pengelola saat membuat atau mengubah barang.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'available' => 'Tersedia',
            'maintenance' => 'Perawatan',
            'retired' => 'Tidak Dipakai',
        ];
    }
}
