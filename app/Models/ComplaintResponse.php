<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintResponse extends Model
{
    use BelongsToEstate;

    /** Balasan komplain mengikuti estate dari komplain induknya. */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        if ($estateId === CurrentEstate::NONE) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('complaint', fn (Builder $q) => $q
            ->where(fn (Builder $e) => $e->where('housing_estate_id', $estateId)->orWhereNull('housing_estate_id')));
    }

    protected $fillable = [
        'complaint_id', 'user_id', 'resident_id', 'message', 'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function authorName(): string
    {
        return $this->user?->name ?? $this->resident?->name ?? 'Pengguna';
    }

    public function isFromStaff(): bool
    {
        return $this->user_id !== null;
    }
}
