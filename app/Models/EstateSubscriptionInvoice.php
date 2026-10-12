<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice langganan SaaS per perumahan: tagihan paket bulanan (base)
 * maupun tagihan kelebihan user (overage). Terpisah dari Billing IPL
 * milik warga agar tidak tercampur dengan keuangan perumahan.
 */
class EstateSubscriptionInvoice extends Model
{
    protected $fillable = [
        'housing_estate_id', 'number', 'type', 'period_label',
        'period_start', 'period_end', 'base_amount', 'included_users',
        'billed_users', 'overage_users', 'overage_per_user', 'overage_amount',
        'total_amount', 'status', 'gateway', 'gateway_reference',
        'payment_url', 'due_at', 'paid_at', 'gateway_payload',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(EstateSubscription::class, 'housing_estate_id', 'housing_estate_id');
    }

    public function isUnpaid(): bool
    {
        return in_array($this->status, ['pending', 'failed', 'expired'], true);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public static function generateNumber(HousingEstate $estate): string
    {
        $prefix = 'SAAS-'.$estate->code.'-'.now()->format('Ymd').'-';

        $seq = static::query()->where('housing_estate_id', $estate->id)->count() + 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
