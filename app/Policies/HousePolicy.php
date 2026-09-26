<?php

namespace App\Policies;

use App\Models\House;
use App\Models\User;

class HousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-houses') || $user->isResident();
    }

    public function view(User $user, House $house): bool
    {
        if ($user->hasPermission('manage-houses')) {
            return true;
        }
        if (! $user->resident_id) {
            return false;
        }

        return $house->houseResidents()->where('resident_id', $user->resident_id)->where('status', 'active')->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-houses');
    }

    public function update(User $user, House $house): bool
    {
        return $user->hasPermission('manage-houses');
    }

    public function delete(User $user, House $house): bool
    {
        return $user->hasPermission('manage-houses');
    }
}
