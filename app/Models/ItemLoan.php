<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Database\Factories\ItemLoanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan dan proses peminjaman barang inventaris.
 *
 * Alur status: requested → approved/rejected → loaned → returned
 * (requested juga bisa dibatalkan pemohon).
 */
class ItemLoan extends Model
{
    /** @use HasFactory<ItemLoanFactory> */
    use BelongsToEstate, HasFactory;

    protected $fillable = [
        'housing_estate_id', 'inventory_item_id', 'resident_id', 'user_id',
        'purpose', 'status', 'approved_by', 'approved_at',
        'handed_over_by', 'loaned_at', 'returned_to', 'returned_at', 'return_note',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'loaned_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function handoverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /**
     * Pinjaman yang belum selesai: belum ditolak, belum dibatalkan, dan
     * barang belum kembali.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['requested', 'approved', 'loaned']);
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return $status !== '' && $status !== null
            ? $query->where('status', $status)
            : $query;
    }

    /**
     * Pengajuan milik warga tertentu.
     */
    public function scopeForResident(Builder $query, int $residentId): Builder
    {
        return $query->where('resident_id', $residentId);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'loaned' => 'Dipinjamkan',
            'returned' => 'Dikembalikan',
            'cancelled' => 'Dibatalkan',
            default => 'Diajukan',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'approved' => 'sky',
            'rejected' => 'red',
            'loaned' => 'purple',
            'returned' => 'green',
            'cancelled' => 'slate',
            default => 'amber',
        };
    }

    /**
     * Nama peminjam: pakai akun pengaju, fallback ke data warga.
     */
    public function borrowerName(): string
    {
        return $this->resident?->name
            ?? $this->requester?->name
            ?? 'Warga';
    }

    /**
     * Rumah peminjam, contoh: "A-1".
     */
    public function borrowerHouseLabel(): ?string
    {
        return $this->resident?->primaryHouse()?->fullLabel();
    }

    /**
     * true bila pengajuan sudah selesai diproses.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['rejected', 'returned', 'cancelled'], true);
    }

    /**
     * true bila barang masih di tangan peminjam.
     */
    public function isOutstanding(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * Status berikutnya yang boleh dipilih pengelola dari status sekarang.
     * Dipakai untuk menentukan tombol aksi mana yang tampil.
     *
     * @return array<int, string>
     */
    public function allowedTransitions(): array
    {
        return match ($this->status) {
            'requested' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['loaned'],
            'loaned' => ['returned'],
            default => [],
        };
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Ringkasan nomor peminjaman, contoh: "PINJAM/202609/000012".
     */
    public function referenceNumber(): string
    {
        return 'PINJAM/'.$this->created_at->format('Ym').'/'
            .str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
