<?php

namespace App\Policies;

use App\Models\ItemLoan;
use App\Models\User;

/**
 * Hak akses pengajuan dan proses peminjaman barang.
 *
 * Warga hanya boleh melihat pengajuan miliknya sendiri, membatalkan selama
 * masih berstatus diajukan, dan mengajukan barang baru. Semua transisi
 * lainnya (setujui, tolak, serahkan, terima kembali) milik pengelola.
 */
class ItemLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-inventory') || $user->resident_id !== null;
    }

    public function view(User $user, ItemLoan $loan): bool
    {
        if ($user->hasPermission('manage-inventory')) {
            return true;
        }

        return $user->resident_id !== null
            && (int) $loan->resident_id === (int) $user->resident_id;
    }

    public function create(User $user): bool
    {
        return $user->resident_id !== null;
    }

    /**
     * Pemohon boleh membatalkan pengajuannya selama belum diproses,
     * supaya tidak ada permintaan menggantung di pengelola.
     */
    public function cancel(User $user, ItemLoan $loan): bool
    {
        return $this->owns($user, $loan) && $loan->canTransitionTo('cancelled');
    }

    /**
     * Menyetujui atau menolak pengajuan.
     */
    public function review(User $user, ItemLoan $loan): bool
    {
        return $user->hasPermission('manage-inventory') && $loan->canTransitionTo('approved');
    }

    /**
     * Menyerahkan barang yang sudah disetujui ke peminjam.
     */
    public function handOver(User $user, ItemLoan $loan): bool
    {
        return $user->hasPermission('manage-inventory') && $loan->canTransitionTo('loaned');
    }

    /**
     * Menerima kembali barang dari peminjam.
     */
    public function receiveReturn(User $user, ItemLoan $loan): bool
    {
        return $user->hasPermission('manage-inventory') && $loan->canTransitionTo('returned');
    }

    public function delete(User $user, ItemLoan $loan): bool
    {
        // Riwayat peminjaman tidak boleh dihapus: menjadi bukti barang keluar
        // dan kembali. Pengelola cukup menutup lewat penolakan/pengembalian.
        return false;
    }

    /**
     * Pemilik pengajuan ditentukan dari relasi resident milik user.
     */
    protected function owns(User $user, ItemLoan $loan): bool
    {
        return $user->resident_id !== null
            && (int) $loan->resident_id === (int) $user->resident_id;
    }
}
