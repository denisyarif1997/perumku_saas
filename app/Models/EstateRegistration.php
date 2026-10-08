<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstateRegistration extends Model
{
    protected $fillable = [
        'user_id', 'bridge_id', 'method',
        'name', 'email', 'phone', 'nik', 'gender', 'birth_date',
        'estate_code', 'estate_name', 'estate_address', 'estate_phone', 'estate_email',
        'block_code', 'block_name', 'house_number',
        'status', 'reject_reason', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bridge(): BelongsTo
    {
        return $this->belongsTo(OAuthAccount::class, 'bridge_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasHouseData(): bool
    {
        return $this->block_code !== null && $this->house_number !== null
            && $this->block_code !== '' && $this->house_number !== '';
    }
}
