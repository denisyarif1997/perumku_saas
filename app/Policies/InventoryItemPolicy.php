<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

/**
 * Hak akses data master barang inventaris.
 *
 * Hanya pengelola (staf dengan permission manage-inventory) yang boleh
 * membuat, mengubah, dan menghapus barang. Warga hanya boleh melihat
 * daftar barang yang tersedia untuk dipinjam.
 */
class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-inventory') || $user->resident_id !== null;
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('manage-inventory');
    }
}
