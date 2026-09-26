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
                ['admin.dashboard', 'layout-dashboard', 'Dashboard', 'admin.dashboard', ['menu-dashboard'], ['view-dashboard']],
            ]],
            ['label' => 'Data Master', 'items' => [
                ['admin.estates.index', 'building-2', 'Perumahan', 'admin.estates.*', ['menu-estates'], ['manage-houses']],
                ['admin.blocks.index', 'grid-2x2', 'Blok', 'admin.blocks.*', ['menu-blocks'], ['manage-houses']],
                ['admin.houses.index', 'house', 'Rumah', 'admin.houses.*', ['menu-houses'], ['manage-houses']],
                ['admin.residents.index', 'users', 'Warga', 'admin.residents.*', ['menu-residents'], ['manage-residents']],
            ]],
            ['label' => 'Keuangan — IPL', 'items' => [
                ['admin.ipl.billings.index', 'file-text', 'Tagihan IPL', 'admin.ipl.billings.*', ['menu-ipl-billings'], ['manage-billing', 'verify-payment']],
                ['admin.ipl.generate', 'calendar-plus', 'Generate Tagihan', 'admin.ipl.generate', ['menu-ipl-generate'], ['manage-billing']],
                ['admin.ipl.payments.index', 'receipt', 'Pembayaran', 'admin.ipl.payments.*', ['menu-ipl-payments'], ['manage-payment', 'verify-payment']],
                ['admin.ipl.rates.index', 'tags', 'Tarif IPL', 'admin.ipl.rates.*', ['menu-ipl-rates'], ['manage-billing']],
            ]],
            ['label' => 'Keuangan — Air', 'items' => [
                ['admin.water.readings', 'droplets', 'Catat Meter', 'admin.water.readings', ['menu-water-readings'], ['manage-billing']],
                ['admin.water.rates.index', 'tags', 'Tarif Air', 'admin.water.rates.*', ['menu-water-rates'], ['manage-billing']],
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
            ['label' => 'Sistem', 'items' => [
                ['admin.users.index', 'user-cog', 'User', 'admin.users.*', ['menu-users'], ['manage-user']],
                ['admin.roles.index', 'shield-check', 'Role & Akses', 'admin.roles.*', ['menu-roles'], ['manage-role']],
                ['admin.activity-logs.index', 'history', 'Log Aktivitas', 'admin.activity-logs.*', ['menu-activity-logs'], ['view-activity-log']],
            ]],
        ];
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
