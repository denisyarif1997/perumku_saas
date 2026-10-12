<?php

namespace App\Services\Billing;

use App\Models\EstateSubscription;
use App\Models\EstateSubscriptionInvoice;
use App\Models\HousingEstate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orkestrasi langganan SaaS: mulai trial, hitung kuota, buat invoice,
 * dan putuskan apakah sebuah estate masih boleh beroperasi.
 *
 * Aturan bisnis:
 * - Trial 15 hari sejak approve, kuota 100 user aktif per perumahan.
 * - Tambah user ke-101+ ditolak kecuali invoice overage periode berjalan lunas.
 * - Trial/tempo habis → status suspended (dikunci), kecuali masih grace period.
 */
class SubscriptionService
{
    public function trialDays(): int
    {
        return (int) config('subscription.trial_days', 15);
    }

    public function includedUsers(): int
    {
        return (int) config('subscription.included_users', 100);
    }

    public function monthlyPrice(): int
    {
        return (int) config('subscription.monthly_price', 149000);
    }

    public function overagePerUser(): int
    {
        return (int) config('subscription.overage_per_user', 1500);
    }

    public function graceDays(): int
    {
        return (int) config('subscription.grace_days', 0);
    }

    public function forEstate(int|HousingEstate $estate): ?EstateSubscription
    {
        $id = $estate instanceof HousingEstate ? $estate->id : $estate;

        return EstateSubscription::query()->withoutGlobalScopes()->where('housing_estate_id', $id)->first();
    }

    /** Mulai trial 15 hari untuk estate yang baru disetujui. Idempotent. */
    public function startTrial(HousingEstate $estate): EstateSubscription
    {
        $existing = $this->forEstate($estate);

        if ($existing) {
            return $existing;
        }

        $now = now();

        return EstateSubscription::query()->withoutGlobalScopes()->create([
            'housing_estate_id' => $estate->id,
            'status' => 'trial',
            'trial_started_at' => $now,
            'trial_ends_at' => $now->copy()->addDays($this->trialDays()),
            'current_period_start' => $now,
            'current_period_end' => $now->copy()->addDays($this->trialDays()),
            'included_users' => $this->includedUsers(),
            'gateway' => (string) config('subscription.gateway', 'midtrans'),
        ]);
    }

    /** Jumlah user aktif estate yang dihitung ke kuota. */
    public function activeUserCount(int|HousingEstate $estate): int
    {
        $id = $estate instanceof HousingEstate ? $estate->id : $estate;

        return User::query()
            ->withoutGlobalScopes()
            ->where('housing_estate_id', $id)
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->count();
    }

    public function overageUsers(int|HousingEstate $estate): int
    {
        $subscription = $this->forEstate($estate);
        $included = $subscription?->included_users ?? $this->includedUsers();

        return max(0, $this->activeUserCount($estate) - (int) $included);
    }

    /**
     * Validasi sebelum user baru dibuat/diaktifkan di estate ini.
     * Melempar RuntimeException bila kuota habis dan overage belum dibayar.
     */
    public function assertCanAddUser(int|HousingEstate $estate): void
    {
        $subscription = $this->forEstate($estate);
        $included = (int) ($subscription?->included_users ?? $this->includedUsers());
        $count = $this->activeUserCount($estate);

        if ($count < $included) {
            return;
        }

        $overage = max(0, ($count + 1) - $included);
        $estateId = $estate instanceof HousingEstate ? $estate->id : $estate;
        $pending = EstateSubscriptionInvoice::query()
            ->where('housing_estate_id', $estateId)
            ->where('type', 'overage')
            ->whereIn('status', ['pending', 'failed', 'expired'])
            ->exists();

        if ($pending || ! $this->overageBillSettled($estate)) {
            throw new RuntimeException(
                "Kuota {$included} user tercapai ({$count} aktif). ".
                "Bayar biaya tambahan untuk {$overage} user kelebihan ".
                '(Rp'.number_format($this->overagePerUser(), 0, ',', '.').'/user/bulan) '.
                'melalui halaman Langganan sebelum menambah user baru.'
            );
        }
    }

    /** Overage lunas bila ada invoice overage paid yang mencakup kelebihan kini. */
    public function overageBillSettled(int|HousingEstate $estate): bool
    {
        $estateId = $estate instanceof HousingEstate ? $estate->id : $estate;
        $overage = $this->overageUsers($estate);

        if ($overage <= 0) {
            return true;
        }

        return EstateSubscriptionInvoice::query()
            ->where('housing_estate_id', $estateId)
            ->where('type', 'overage')
            ->where('status', 'paid')
            ->where('overage_users', '>=', $overage)
            ->where(function ($q) {
                $q->whereNull('period_end')->orWhereDate('period_end', '>=', now()->toDateString());
            })
            ->exists();
    }

    /** Buat (atau ambil) invoice paket bulanan periode berjalan. Idempotent. */
    public function ensureBaseInvoice(HousingEstate $estate): EstateSubscriptionInvoice
    {
        $subscription = $this->forEstate($estate) ?? $this->startTrial($estate);
        $start = ($subscription->current_period_start ?? now())->copy()->startOfDay();
        $end = ($subscription->current_period_end ?? now()->copy()->addMonth())->copy()->endOfDay();
        $label = $start->format('M Y');

        $existing = EstateSubscriptionInvoice::query()
            ->where('housing_estate_id', $estate->id)
            ->where('type', 'base')
            ->where('period_label', $label)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(fn () => EstateSubscriptionInvoice::create([
            'housing_estate_id' => $estate->id,
            'number' => EstateSubscriptionInvoice::generateNumber($estate),
            'type' => 'base',
            'period_label' => $label,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'base_amount' => $this->monthlyPrice(),
            'included_users' => (int) $subscription->included_users,
            'billed_users' => $this->activeUserCount($estate),
            'overage_per_user' => $this->overagePerUser(),
            'total_amount' => $this->monthlyPrice(),
            'status' => 'pending',
            'gateway' => (string) config('subscription.gateway', 'midtrans'),
            'due_at' => $end,
        ]));
    }

    /** Buat invoice overage untuk kelebihan user saat ini. */
    public function createOverageInvoice(HousingEstate $estate): EstateSubscriptionInvoice
    {
        $subscription = $this->forEstate($estate) ?? $this->startTrial($estate);
        $overage = $this->overageUsers($estate);

        if ($overage <= 0) {
            throw new RuntimeException('Tidak ada kelebihan user untuk ditagih.');
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $amount = $overage * $this->overagePerUser();

        return DB::transaction(fn () => EstateSubscriptionInvoice::create([
            'housing_estate_id' => $estate->id,
            'number' => EstateSubscriptionInvoice::generateNumber($estate),
            'type' => 'overage',
            'period_label' => $start->format('M Y').' (kelebihan user)',
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'included_users' => (int) $subscription->included_users,
            'billed_users' => $this->activeUserCount($estate),
            'overage_users' => $overage,
            'overage_per_user' => $this->overagePerUser(),
            'overage_amount' => $amount,
            'total_amount' => $amount,
            'status' => 'pending',
            'gateway' => (string) config('subscription.gateway', 'midtrans'),
            'due_at' => $end,
        ]));
    }

    /** Tandai invoice lunas (dipakai webhook gateway / verifikasi manual). */
    public function markPaid(EstateSubscriptionInvoice $invoice, array $gatewayPayload = []): EstateSubscriptionInvoice
    {
        return DB::transaction(function () use ($invoice, $gatewayPayload) {
            if ($invoice->status === 'paid') {
                return $invoice;
            }

            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_payload' => $gatewayPayload !== [] ? json_encode($gatewayPayload) : $invoice->gateway_payload,
            ])->save();

            $subscription = $this->forEstate($invoice->housing_estate_id);

            if ($subscription && $invoice->type === 'base') {
                $base = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                    ? $subscription->current_period_end->copy()
                    : now();
                $subscription->forceFill([
                    'status' => 'active',
                    'current_period_start' => $base->copy()->startOfDay(),
                    'current_period_end' => $base->copy()->addMonth()->endOfDay(),
                    'grace_ends_at' => null,
                    'suspended_at' => null,
                ])->save();
            }

            return $invoice->fresh();
        });
    }

    /** Apakah estate boleh beroperasi (belum dikunci). */
    public function isEstateOperational(int|HousingEstate $estate): bool
    {
        $subscription = $this->forEstate($estate);

        if (! $subscription) {
            return true;
        }

        return $subscription->isActive();
    }

    /** Hitung ulang status satu subscription berdasarkan waktu & tagihan. */
    public function refreshStatus(EstateSubscription $subscription): EstateSubscription
    {
        $now = now();

        if ($subscription->status === 'trial' && $subscription->trial_ends_at && $now->greaterThan($subscription->trial_ends_at)) {
            if ($this->graceDays() > 0) {
                $subscription->forceFill([
                    'status' => 'past_due',
                    'grace_ends_at' => $subscription->trial_ends_at->copy()->addDays($this->graceDays()),
                ])->save();
            } else {
                $subscription->forceFill(['status' => 'suspended', 'suspended_at' => $now])->save();
            }

            return $subscription->fresh();
        }

        if (in_array($subscription->status, ['active', 'past_due'], true)
            && $subscription->current_period_end && $now->greaterThan($subscription->current_period_end)) {
            $graceEnds = $subscription->grace_ends_at
                ?? $subscription->current_period_end->copy()->addDays($this->graceDays());

            if ($now->lessThanOrEqualTo($graceEnds)) {
                $subscription->forceFill(['status' => 'past_due', 'grace_ends_at' => $graceEnds])->save();
            } else {
                $subscription->forceFill(['status' => 'suspended', 'suspended_at' => $now])->save();
            }
        }

        return $subscription->fresh();
    }

    /** Scheduler harian: suspend yang lewat trial/tempo + grace period. */
    public function sweepExpired(): int
    {
        $suspended = 0;

        EstateSubscription::query()->withoutGlobalScopes()
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->chunkById(100, function ($rows) use (&$suspended) {
                foreach ($rows as $subscription) {
                    if ($this->refreshStatus($subscription)->status === 'suspended') {
                        $suspended++;
                    }
                }
            });

        return $suspended;
    }
}
