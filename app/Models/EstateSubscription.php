<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Langganan SaaS per perumahan.
 *
 * Satu baris per HousingEstate: masa trial 15 hari, kuota user yang
 * termasuk paket, dan periode berjalan. Dibuat otomatis saat pengajuan
 * perumahan disetujui (lihat EstateRegistrationService).
 */
class EstateSubscription extends Model
{
    protected $fillable = [
        'housing_estate_id', 'status', 'trial_started_at', 'trial_ends_at',
        'current_period_start', 'current_period_end', 'grace_ends_at',
        'included_users', 'gateway', 'gateway_customer_id', 'gateway_subscription_id',
        'suspended_at', 'cancelled_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function isActive(): bool
    {
        if (in_array($this->status, ['trial', 'active'], true)) {
            return true;
        }

        if ($this->status === 'past_due' && $this->grace_ends_at && $this->grace_ends_at->isFuture()) {
            return true;
        }

        return false;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isTrial() || ! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->copy()->startOfDay(), false));
    }

    public function trialExpired(): bool
    {
        return $this->isTrial() && $this->trial_ends_at && now()->greaterThan($this->trial_ends_at);
    }

    /** Jumlah user aktif yang dihitung ke kuota (tanpa super_admin platform). */
    public function activeUserCount(): int
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('housing_estate_id', $this->housing_estate_id)
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->count();
    }

    public function overageUsers(): int
    {
        return max(0, $this->activeUserCount() - (int) $this->included_users);
    }
}
