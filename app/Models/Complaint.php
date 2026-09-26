<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected $fillable = [
        'ticket_number', 'housing_estate_id', 'resident_id', 'house_id',
        'assigned_to', 'title', 'description', 'category', 'priority',
        'status', 'resolved_at', 'resolved_by', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Nomor tiket diisi otomatis setelah baris tersimpan (butuh ID).
        static::created(function (self $complaint): void {
            if (filled($complaint->ticket_number)) {
                return;
            }

            $complaint->ticket_number = static::generateNumber($complaint);

            static::withoutTimestamps(fn () => $complaint->newQuery()
                ->whereKey($complaint->getKey())
                ->update(['ticket_number' => $complaint->ticket_number]));
        });
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ComplaintResponse::class)->orderBy('created_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', ['resolved', 'closed']);
    }

    /**
     * Nomor tiket deterministik berbasis ID, contoh: ADU/202609/000123
     */
    public static function generateNumber(self $complaint): string
    {
        return 'ADU/'.now()->format('Ym').'/'
            .str_pad((string) $complaint->id, 6, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'Diproses',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            default => 'Baru',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'in_progress' => 'amber',
            'resolved' => 'green',
            'closed' => 'slate',
            default => 'sky',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'high' => 'Tinggi',
            'low' => 'Rendah',
            default => 'Normal',
        };
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'high' => 'red',
            'low' => 'slate',
            default => 'sky',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'water' => 'Air',
            'electricity' => 'Listrik',
            'security' => 'Keamanan',
            'cleanliness' => 'Kebersihan',
            'facility' => 'Fasilitas',
            'neighbor' => 'Tetangga',
            default => 'Umum',
        };
    }
}
