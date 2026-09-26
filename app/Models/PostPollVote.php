<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu suara warga pada polling sebuah postingan.
 *
 * Tanpa soft delete: suara tidak bermakna tanpa postingannya, jadi tidak
 * perlu jejak riwayat seperti komentar. Karena penghapusan tetap memakai
 * klausa WHERE, suara tidak boleh dihapus lewat relasi yang sudah
 * dikenai global scope estate (yang menambah WHERE EXISTS ke tabel posts)
 * — MySQL menolak subquery yang mengacu ke tabel target DELETE. Gunakan
 * Post::deletePollVotes() yang sudah melewati scope tersebut.
 */
class PostPollVote extends Model
{
    use BelongsToEstate;

    /** Suara mengikuti estate dari thread induknya. */
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
        'post_id', 'resident_id', 'user_id', 'option_key',
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
}
