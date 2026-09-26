<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostComment extends Model
{
    use BelongsToEstate, SoftDeletes;

    /** Komentar mengikuti estate dari thread induknya. */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        if ($estateId === CurrentEstate::NONE) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('post', fn (Builder $q) => $q
            ->where(fn (Builder $e) => $e->where('housing_estate_id', $estateId)->orWhereNull('housing_estate_id')));
    }

    protected $fillable = [
        'post_id', 'resident_id', 'user_id', 'body',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Nama komentator: pakai akun user, fallback ke data warga.
     */
    public function authorName(): string
    {
        return $this->author?->name ?? $this->resident?->name ?? 'Warga';
    }
}
