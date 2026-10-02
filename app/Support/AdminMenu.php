<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\User;

/**
 * Definisi menu admin berbasis permission (RBAC per menu).
 * Setiap item: [route, icon, label, pattern, permissions].
 * Item hanya tampil bila user memiliki salah satu permission tsb;
 * grup tanpa item yang lolos disembunyikan seluruhnya.
 */
class AdminMenu
{
    public static function sections(): array
    {
        return [
            ['label' => null, 'items' => [
                // Item platform dibatasi peran, bukan permission: isinya lintas
                // seluruh condominan sehingga tidak boleh tampil untuk admin biasa.
                ['platform.dashboard', 'globe', 'Dashboard Super Admin', 'platform.*', ['menu-dashboard'], [], ['super_admin']],
                ['admin.dashboard', 'layout-dashboard', 'Dashboard', 'admin.dashboard', ['menu-dashboard'], ['view-dashboard']],
            ]],
            ['label' => 'Data Master', 'items' => [
                ['admin.estates.index', 'building-2', 'Perumahan', 'admin.estates.*', ['menu-estates'], ['manage-houses']],
                ['admin.blocks.index', 'grid-2x2', 'Blok', 'admin.blocks.*', ['menu-blocks'], ['manage-houses']],
                ['admin.houses.index', 'house', 'Rumah', 'admin.houses.*', ['menu-houses'], ['manage-houses']],
                ['admin.residents.index', 'users', 'Warga', 'admin.residents.*', ['menu-residents'], ['manage-residents']],
            ]],
            ['label' => 'Keuangan — Iuran', 'items' => [
                ['admin.ipl.billings.index', 'file-text', 'Tagihan Iuran', 'admin.ipl.billings.*', ['menu-ipl-billings'], ['manage-billing', 'verify-payment']],
                ['admin.ipl.generate', 'calendar-plus', 'Generate Tagihan', 'admin.ipl.generate', ['menu-ipl-generate'], ['manage-billing']],
                ['admin.ipl.payments.index', 'receipt', 'Pembayaran', 'admin.ipl.payments.*', ['menu-ipl-payments'], ['manage-payment', 'verify-payment']],
                ['admin.ipl.rates.index', 'tags', 'Tarif Iuran', 'admin.ipl.rates.*', ['menu-ipl-rates'], ['manage-billing']],
            ]],

            ['label' => 'Keuangan — Kas Warga', 'items' => [
                ['admin.cash.accounts.index', 'wallet', 'Daftar Kas', 'admin.cash.accounts.*', ['menu-cash-accounts'], ['manage-finance']],
                ['admin.cash.transactions.index', 'arrow-left-right', 'Transaksi Kas', 'admin.cash.transactions.*', ['menu-cash-transactions'], ['manage-finance']],
            ]],
            ['label' => 'Info & Layanan', 'items' => [
                ['admin.info.announcements', 'megaphone', 'Pengumuman', 'admin.info.announcements', ['menu-announcements'], ['manage-announcement']],
                ['admin.info.complaints', 'message-square-warning', 'Laporan Warga', 'admin.info.complaints*', ['menu-complaints'], ['manage-complaint']],
                ['admin.forum.index', 'messages-square', 'Forum Warga', 'admin.forum.*', ['menu-forum'], ['manage-forum']],
            ]],
            ['label' => 'Inventaris', 'items' => [
                ['admin.inventory.items.index', 'package', 'Daftar Barang', 'admin.inventory.items.*', ['menu-inventory-items'], ['manage-inventory']],
                ['admin.inventory.loans.index', 'repeat', 'Pinjam Barang', 'admin.inventory.loans.*', ['menu-inventory-loans'], ['manage-inventory']],
            ]],
            ['label' => 'Sistem', 'items' => [
                ['admin.users.index', 'user-cog', 'User', 'admin.users.*', ['menu-users'], ['manage-user']],
                ['admin.roles.index', 'shield-check', 'Role & Akses', 'admin.roles.*', ['menu-roles'], ['manage-role']],
                ['admin.activity-logs.index', 'history', 'Log Aktivitas', 'admin.activity-logs.*', ['menu-activity-logs'], ['view-activity-log']],
            ]],
        ];
    }

    /**
     * Route admin pertama yang benar-benar boleh diakses user, atau null bila
     * user tidak punya akses area admin sama sekali.
     *
     * Dipakai oleh kedua arah perpindahan tampilan: tombol di area warga
     * (header + halaman profil) untuk kembali ke admin.
     *
     * Gerbahnya memakai permission (access-admin) yang sama dengan middleware
     * di routes/web.php, bukan nama role. Role bisa diubah bebas lewat halaman
     * Role & Akses, sehingga memfilternya per role membuat tombol ini muncul
     * untuk user yang akhirnya kena 403 — atau hilang untuk user yang
     * sebenarnya boleh masuk.
     */
    public static function landingRouteFor(User $user): ?string
    {
        if (! $user->hasPermission('access-admin')) {
            return null;
        }

        if ($user->hasPermission('view-dashboard')) {
            return 'admin.dashboard';
        }

        // Tanpa izin lihat dashboard, arahkan ke modul pertama yang boleh dibuka.
        // Yang dicek adalah permission AKSI (penjaga route), bukan permission menu
        // (filter sidebar), supaya role hasil editan manual tetap punya tujuan
        // yang benar-benar bisa dimuat.
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $roles = $item[6] ?? [];
                if ($roles !== [] && ! $user->hasRole(...$roles)) {
                    continue;
                }

                [$route, , , , , $actionSlugs] = $item;

                if ($user->hasPermission(...$actionSlugs)) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Filter menu sesuai permission user. Setiap item dicek dengan permission
     * per-menu (menu-...) terlebih dahulu. Jika permission per-menu tersebut
     * belum terdaftar di database (sebelum seeder dijalankan ulang), fallback
     * ke permission aksi lama agar menu tidak tiba-tiba hilang.
     *
     * Nilai kembalian hanya berisi [route, icon, label, pattern] yang lolos.
     *
     * @return array<int, array{label: ?string, items: array<int, array<int, string>>}>
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $menuPermsExist = Permission::query()->where('group', 'menu')->exists();

        return collect(self::sections())
            ->map(function (array $section) use ($user, $menuPermsExist) {
                $visibleItems = [];

                foreach ($section['items'] as $item) {
                    // Elemen ke-7 (opsional) membatasi item ini ke peran tertentu.
                    $roles = $item[6] ?? [];
                    if ($roles !== [] && ! $user->hasRole(...$roles)) {
                        continue;
                    }

                    [$route, $icon, $label, $pattern, $menuSlugs, $actionSlugs] = $item;
                    $check = $menuPermsExist ? $menuSlugs : $actionSlugs;
                    if ($user->hasPermission(...$check)) {
                        $visibleItems[] = [$route, $icon, $label, $pattern];
                    }
                }

                return ['label' => $section['label'], 'items' => $visibleItems];
            })
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }
}
