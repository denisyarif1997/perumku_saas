<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-complaint') || $user->resident_id !== null;
    }

    public function view(User $user, Complaint $complaint): bool
    {
        if ($user->hasPermission('manage-complaint')) {
            return true;
        }

        return $user->resident_id && (int) $complaint->resident_id === (int) $user->resident_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-complaint') || $user->resident_id !== null;
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $user->hasPermission('manage-complaint');
    }

    public function respond(User $user, Complaint $complaint): bool
    {
        return $user->hasPermission('manage-complaint');
    }

    public function delete(User $user, Complaint $complaint): bool
    {
        return $user->hasPermission('manage-complaint');
    }
}
