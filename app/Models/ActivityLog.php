<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use BelongsToEstate;

    public $timestamps = true;

    /** Log aktivitas mengikuti estate dari pelakunya (user). */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        if ($estateId === CurrentEstate::NONE) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('user', fn (Builder $q) => $q
            ->where(fn (Builder $u) => $u->where('housing_estate_id', $estateId)->orWhereNull('housing_estate_id')));
    }

    protected $fillable = [
        'user_id', 'action', 'module', 'subject_type', 'subject_id',
        'description', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(array $data): self
    {
        $data['ip_address'] ??= request()?->ip();
        $data['user_agent'] ??= request()?->userAgent();

        return static::create($data);
    }
}
