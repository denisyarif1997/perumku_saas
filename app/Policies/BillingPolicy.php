<?php

namespace App\Policies;

use App\Models\Billing;
use App\Models\User;

class BillingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-billing')
            || $user->hasPermission('verify-payment')
            || $user->isResident();
    }

    public function view(User $user, Billing $billing): bool
    {
        if ($user->hasPermission('manage-billing') || $user->hasPermission('verify-payment')) {
            return true;
        }

        return $this->ownsBilling($user, $billing);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-billing');
    }

    public function update(User $user, Billing $billing): bool
    {
        return $user->hasPermission('manage-billing');
    }

    public function delete(User $user, Billing $billing): bool
    {
        return $user->hasPermission('manage-billing');
    }

    /**
     * Warga boleh membayar tagihan miliknya yang belum lunas.
     */
    public function pay(User $user, Billing $billing): bool
    {
        if ($user->hasPermission('manage-payment')) {
            return true;
        }

        return $this->ownsBilling($user, $billing)
            && in_array($billing->status, ['unpaid', 'partial'], true);
    }

    protected function ownsBilling(User $user, Billing $billing): bool
    {
        if (! $user->resident_id) {
            return false;
        }

        if ((int) $billing->resident_id === (int) $user->resident_id) {
            return true;
        }

        return (bool) $billing->house?->houseResidents()
            ->where('resident_id', $user->resident_id)
            ->where('status', 'active')
            ->exists();
    }
}
