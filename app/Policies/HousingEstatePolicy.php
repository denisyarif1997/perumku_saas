<?php

namespace App\Policies;

use App\Models\HousingEstate;
use App\Models\User;

class HousingEstatePolicy
{
    /**
     * Manajemen perumahan (daftar tenant) hanya untuk platform super_admin.
     * Admin estate bekerja di dalam estate-nya sendiri dan tidak boleh
     * membuat, mengubah, atau menghapus entri HousingEstate.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, HousingEstate $housingEstate): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, HousingEstate $housingEstate): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, HousingEstate $housingEstate): bool
    {
        return $user->hasRole('super_admin');
    }
}
