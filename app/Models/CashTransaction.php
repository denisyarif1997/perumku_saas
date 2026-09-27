<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use BelongsToEstate, SoftDeletes;

    /**
     * Mutasi kas mengikuti estate dari akun kasnya.
     *
     * Akun kas boleh ber-housing_estate_id NULL (kas bersama untuk seluruh
     * perumahan), jadi aturan ini sama dengan CashAccount: baris estate X
     * PLUS baris global. Tanpa orWhereNull di sini, transaksi kas bersama
     * hilang dari penghuni estate — akunnya tetap tampil di Daftar Kas, tetapi
     * riwayatnya tidak terlihat dan kolom Saldo Sekarang hanya menghitung
     * saldo awal.
     */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        $accountInScope = fn (Builder $account) => $account
            ->where(fn (Builder $row) => $row
                ->where('housing_estate_id', $estateId)
                ->orWhereNull('housing_estate_id'));

        $query->where(function (Builder $q) use ($accountInScope) {
            $q->whereHas('account', $accountInScope)
                ->orWhereHas('destinationAccount', $accountInScope);
        });
    }

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_REVERSAL = 'reversal';

    use SoftDeletes;

    protected $fillable = [
        'cash_account_id', 'destination_account_id', 'payment_id', 'type', 'amount',
        'transaction_date', 'category', 'reference', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'destination_account_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Label tipe transaksi dalam Bahasa Indonesia.
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IN => 'Kas Masuk',
            self::TYPE_OUT => 'Kas Keluar',
            self::TYPE_TRANSFER => 'Transfer',
            self::TYPE_REVERSAL => 'Pembalikan Kas',
            default => $this->type,
        };
    }

    /**
     * Dampak transaksi pada saldo akun sumber (positif/nol/negatif).
     */
    public function sourceImpact(): float
    {
        return match ($this->type) {
            self::TYPE_IN => (float) $this->amount,
            self::TYPE_OUT, self::TYPE_TRANSFER, self::TYPE_REVERSAL => -1 * (float) $this->amount,
            default => 0.0,
        };
    }

    /**
     * Catat kas masuk otomatis dari pembayaran IPL yang diverifikasi.
     */
    public static function recordForPayment(Payment $payment, CashAccount $account, int $userId): ?self
    {
        // Hindari duplikat: satu pembayaran hanya boleh masuk kas satu kali.
        if (static::where('payment_id', $payment->id)->where('type', self::TYPE_IN)->exists()) {
            return null;
        }

        $billing = $payment->billing;

        return static::create([
            'cash_account_id' => $account->id,
            'payment_id' => $payment->id,
            'type' => self::TYPE_IN,
            'amount' => $payment->amount,
            'transaction_date' => $payment->payment_date ?? now()->toDateString(),
            'category' => 'ipl',
            'reference' => $payment->payment_number,
            'description' => 'Pembayaran '.($billing?->typeLabel() ?? 'IPL').' '.$billing?->invoice_number
                .' — '.($payment->resident?->name ?? 'warga'),
            'created_by' => $userId,
        ]);
    }

    /**
     * Balikkan seluruh transaksi kas masuk dari sebuah pembayaran
     * (dipakai saat tagihan dibatalkan / dihapus).
     */
    public static function reverseForPayment(Payment $payment, int $userId): int
    {
        $entries = static::where('payment_id', $payment->id)
            ->where('type', self::TYPE_IN)
            ->whereDoesntHave('reversals')
            ->get();

        foreach ($entries as $entry) {
            static::create([
                'cash_account_id' => $entry->cash_account_id,
                'payment_id' => $payment->id,
                'type' => self::TYPE_REVERSAL,
                'amount' => $entry->amount,
                'transaction_date' => now()->toDateString(),
                'category' => 'ipl',
                'reference' => $payment->payment_number,
                'description' => 'Pembalikan kas masuk '.$payment->payment_number,
                'created_by' => $userId,
            ]);
        }

        return $entries->count();
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'payment_id', 'payment_id')
            ->where('type', self::TYPE_REVERSAL);
    }
}
