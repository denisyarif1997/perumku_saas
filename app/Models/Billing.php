<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Models\Scopes\BelongsToEstateScope;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Billing extends Model
{
    use BelongsToEstate, SoftDeletes;

    protected static function booted(): void
    {
        // Kolom estate yang dinormalisasi tidak boleh kosong: tagihan selalu
        // punya rumah, dan rumah selalu punya estate. Kalau pemanggil tidak
        // menyediakannya, turunkan dari house — tanpa global scope, karena pada
        // saat ini belum ada konteks tenant yang bisa dipercaya.
        //
        // Tanpa ini, satu baris dengan housing_estate_id null akan hilang dari
        // daftar tagihan seluruh admin estate, hanya terlihat oleh super_admin.
        static::creating(function (self $billing): void {
            if ($billing->housing_estate_id === null && $billing->house_id !== null) {
                $billing->housing_estate_id = House::withoutGlobalScope(BelongsToEstateScope::class)
                    ->whereKey($billing->house_id)
                    ->value('housing_estate_id');
            }
        });
    }

    /**
     * Tagihan memakai kolom housing_estate_id yang dinormalisasi (lihat migrasi
     * add_housing_estate_id_to_billings_and_payments_table), bukan relasi ke house.
     *
     * Penyaringan sengaja ketat tanpa orWhereNull: tagihan tanpa estate adalah
     * kondisi data rusak, dan lebih baik tidak terlihat sama sekali daripada
     * bocor ke daftar tagihan setiap peripheran.
     */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        $query->where('housing_estate_id', $estateId);
    }

    protected $fillable = [
        'invoice_number', 'house_id', 'housing_estate_id', 'resident_id', 'ipl_rate_id', 'water_rate_id',
        'billing_type', 'period_month', 'period_year', 'amount', 'discount', 'total',
        'paid_amount', 'due_date', 'status', 'notes', 'created_by',
        'meter_start', 'meter_end', 'usage_m3',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'meter_start' => 'decimal:2',
            'meter_end' => 'decimal:2',
            'usage_m3' => 'decimal:2',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function iplRate(): BelongsTo
    {
        return $this->belongsTo(IplRate::class);
    }

    public function waterRate(): BelongsTo
    {
        return $this->belongsTo(WaterRate::class);
    }

    public function meterReading(): HasOne
    {
        return $this->hasOne(WaterMeterReading::class, 'billing_id');
    }

    public function isWater(): bool
    {
        return ($this->billing_type ?? 'ipl') === 'water';
    }

    public function typeLabel(): string
    {
        return $this->isWater() ? 'Air' : 'IPL';
    }

    /**
     * Nama tarif sesuai jenis tagihan (air memakai tarif air, IPL memakai tarif IPL).
     */
    public function rateName(): string
    {
        return $this->isWater()
            ? ($this->waterRate?->name ?? 'Tarif air')
            : ($this->iplRate?->name ?? 'Tarif tidak tercatat');
    }

    public function scopeIpl(Builder $query): Builder
    {
        return $query->where('billing_type', 'ipl');
    }

    public function scopeWater(Builder $query): Builder
    {
        return $query->where('billing_type', 'water');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', 'verified');
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function periodLabel(): string
    {
        return Currency::period((int) $this->period_year, (int) $this->period_month);
    }

    public function remaining(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function isOverdue(): bool
    {
        if (! in_array($this->status, ['unpaid', 'partial'], true) || ! $this->due_date) {
            return false;
        }

        return $this->due_date->startOfDay()->lt(now()->startOfDay());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Lunas',
            'partial' => 'Bayar Sebagian',
            'cancelled' => 'Dibatalkan',
            default => $this->isOverdue() ? 'Terlambat' : 'Belum Bayar',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'paid' => 'green',
            'partial' => 'amber',
            'cancelled' => 'slate',
            default => $this->isOverdue() ? 'red' : 'sky',
        };
    }

    /**
     * Hitung ulang paid_amount & status dari pembayaran yang sudah diverifikasi.
     */
    public function syncPaymentStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $paid = (float) $this->payments()->where('status', 'verified')->sum('amount');
        $total = (float) $this->total;

        $status = 'unpaid';
        if ($total > 0 && $paid >= $total) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        $this->update(['paid_amount' => $paid, 'status' => $status]);
    }
}
