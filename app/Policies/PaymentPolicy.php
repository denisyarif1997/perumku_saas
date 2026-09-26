<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-payment')
            || $user->hasPermission('verify-payment')
            || $user->isResident();
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasPermission('manage-payment') || $user->hasPermission('verify-payment')) {
            return true;
        }

        return $this->isOwnPayment($user, $payment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-payment') || $user->isResident();
    }

    public function verify(User $user): bool
    {
        return $user->hasPermission('verify-payment');
    }

    public function reject(User $user): bool
    {
        return $user->hasPermission('verify-payment');
    }

    /**
     * Pengaju hanya boleh menghapus pembayaran yang masih pending.
     */
    public function delete(User $user, Payment $payment): bool
    {
        if ($user->hasPermission('manage-payment')) {
            return true;
        }

        return $payment->status === 'pending' && $this->isOwnPayment($user, $payment);
    }

    protected function isOwnPayment(User $user, Payment $payment): bool
    {
        return $user->resident_id && (int) $payment->resident_id === (int) $user->resident_id;
    }
}
