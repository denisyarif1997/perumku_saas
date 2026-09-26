<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-residents') || $user->isResident();
    }

    public function view(User $user, Resident $resident): bool
    {
        if ($user->hasPermission('manage-residents')) {
            return true;
        }

        return $user->resident_id === $resident->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-residents');
    }

    public function update(User $user, Resident $resident): bool
    {
        if ($user->hasPermission('manage-residents')) {
            return true;
        }

        return $user->resident_id === $resident->id;
    }

    public function delete(User $user, Resident $resident): bool
    {
        return $user->hasPermission('manage-residents');
    }
}
