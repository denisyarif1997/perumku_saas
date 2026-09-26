<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected $fillable = [
        'housing_estate_id', 'user_id', 'title', 'content', 'category',
        'priority', 'is_pinned', 'published_at', 'expired_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q
                ->whereNull('expired_at')
                ->orWhere('expired_at', '>', now()));
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'maintenance' => 'Pemeliharaan',
            'event' => 'Acara',
            'security' => 'Keamanan',
            'billing' => 'Tagihan',
            'urgent' => 'Darurat',
            default => 'Umum',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'high' => 'Penting',
            'low' => 'Biasa',
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
}
