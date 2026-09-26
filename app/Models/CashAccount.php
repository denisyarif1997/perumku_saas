<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected $fillable = [
        'housing_estate_id', 'name', 'type', 'account_number', 'account_holder',
        'opening_balance', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'destination_account_id');
    }

    /**
     * Total transaksi kas masuk (in) pada akun ini.
     */
    public function totalIn(): float
    {
        return (float) $this->transactions()->where('type', 'in')->sum('amount');
    }

    /**
     * Total transaksi kas keluar (out) pada akun ini.
     */
    public function totalOut(): float
    {
        return (float) $this->transactions()->where('type', 'out')->sum('amount');
    }

    /**
     * Total pengembalian kas (reversal) pada akun ini, mis. dari pembatalan tagihan.
     */
    public function totalReversal(): float
    {
        return (float) $this->transactions()->where('type', 'reversal')->sum('amount');
    }

    /**
     * Total transfer yang keluar dari akun ini.
     */
    public function totalTransferOut(): float
    {
        return (float) $this->transactions()->where('type', 'transfer')->sum('amount');
    }

    /**
     * Total transfer yang masuk ke akun ini.
     */
    public function totalTransferIn(): float
    {
        return (float) $this->incomingTransfers()->sum('amount');
    }

    /**
     * Saldo saat ini = saldo awal + masuk + transfer masuk - keluar - transfer keluar - pembalikan.
     */
    public function currentBalance(): float
    {
        return round($this->opening_balance + $this->totalIn() + $this->totalTransferIn()
            - $this->totalOut() - $this->totalTransferOut() - $this->totalReversal(), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function label(): string
    {
        return $this->name.' — '.($this->estate?->name ?? 'Semua Perumahan');
    }
}
