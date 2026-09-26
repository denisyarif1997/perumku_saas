<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Akses semua fitur'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Operasional perumahan'],
            ['name' => 'Finance', 'slug' => 'finance', 'description' => 'Keuangan & IPL'],
            ['name' => 'RT', 'slug' => 'rt', 'description' => 'Data warga & administrasi'],
            ['name' => 'RW', 'slug' => 'rw', 'description' => 'Monitoring wilayah'],
            ['name' => 'Security', 'slug' => 'security', 'description' => 'Tamu, kendaraan, paket'],
            ['name' => 'Maintenance', 'slug' => 'maintenance', 'description' => 'Pengaduan & maintenance'],
            ['name' => 'Resident', 'slug' => 'resident', 'description' => 'Warga'],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(['slug' => $data['slug']], $data + ['status' => 'active']);
        }

        $permissions = [
            'general' => ['view-dashboard', 'access-admin'],
            'master' => ['manage-houses', 'manage-residents'],
            'billing' => ['manage-billing', 'manage-payment', 'verify-payment'],
            'communication' => ['manage-announcement', 'manage-event', 'manage-forum'],
            'service' => ['manage-complaint', 'manage-administration', 'manage-facility', 'manage-booking'],
            'marketplace' => ['manage-marketplace'],
            'security' => ['manage-visitor', 'manage-vehicle', 'manage-package', 'manage-emergency'],
            'finance' => ['manage-finance'],
            'system' => ['manage-user', 'manage-role', 'view-activity-log'],
        ];

        $labels = [
            'view-dashboard' => 'Lihat Dashboard',
            'access-admin' => 'Akses Area Admin',
            'manage-houses' => 'Kelola Rumah', 'manage-residents' => 'Kelola Warga',
            'manage-billing' => 'Kelola Tagihan', 'manage-payment' => 'Kelola Pembayaran', 'verify-payment' => 'Verifikasi Pembayaran',
            'manage-announcement' => 'Kelola Pengumuman',
            'manage-event' => 'Kelola Event', 'manage-forum' => 'Kelola Forum',
            'manage-complaint' => 'Kelola Pengaduan', 'manage-administration' => 'Kelola Administrasi',
            'manage-facility' => 'Kelola Fasilitas', 'manage-booking' => 'Kelola Booking',
            'manage-marketplace' => 'Kelola Marketplace',
            'manage-visitor' => 'Kelola Tamu', 'manage-vehicle' => 'Kelola Kendaraan',
            'manage-package' => 'Kelola Paket', 'manage-emergency' => 'Kelola Emergency',
            'manage-finance' => 'Kelola Keuangan',
            'manage-user' => 'Kelola User', 'manage-role' => 'Kelola Role', 'view-activity-log' => 'Lihat Activity Log',
        ];

        $permIds = [];
        foreach ($permissions as $group => $slugs) {
            foreach ($slugs as $slug) {
                $perm = Permission::firstOrCreate(['slug' => $slug], [
                    'name' => $labels[$slug] ?? $slug,
                    'group' => $group,
                ]);
                $permIds[$slug] = $perm->id;
            }
        }

        // Permission per menu (RBAC per menu): tiap item sidebar punya
        // permission sendiri sehingga bisa diberikan per menu di halaman Role.
        $menuMap = [
            'menu-dashboard' => ['view-dashboard'],
            'menu-estates' => ['manage-houses'],
            'menu-blocks' => ['manage-houses'],
            'menu-houses' => ['manage-houses'],
            'menu-residents' => ['manage-residents'],
            'menu-ipl-billings' => ['manage-billing', 'verify-payment'],
            'menu-ipl-generate' => ['manage-billing'],
            'menu-ipl-payments' => ['manage-payment', 'verify-payment'],
            'menu-ipl-rates' => ['manage-billing'],
            'menu-water-readings' => ['manage-billing'],
            'menu-water-rates' => ['manage-billing'],
            'menu-cash-accounts' => ['manage-finance'],
            'menu-cash-transactions' => ['manage-finance'],
            'menu-announcements' => ['manage-announcement'],
            'menu-complaints' => ['manage-complaint'],
            'menu-forum' => ['manage-forum'],
            'menu-users' => ['manage-user'],
            'menu-roles' => ['manage-role'],
            'menu-activity-logs' => ['view-activity-log'],
        ];

        $menuLabels = [
            'menu-dashboard' => 'Menu: Dashboard',
            'menu-estates' => 'Menu: Perumahan',
            'menu-blocks' => 'Menu: Blok',
            'menu-houses' => 'Menu: Rumah',
            'menu-residents' => 'Menu: Warga',
            'menu-ipl-billings' => 'Menu: Tagihan IPL',
            'menu-ipl-generate' => 'Menu: Generate Tagihan',
            'menu-ipl-payments' => 'Menu: Pembayaran',
            'menu-ipl-rates' => 'Menu: Tarif IPL',
            'menu-water-readings' => 'Menu: Catat Meter Air',
            'menu-water-rates' => 'Menu: Tarif Air',
            'menu-cash-accounts' => 'Menu: Daftar Kas',
            'menu-cash-transactions' => 'Menu: Transaksi Kas',
            'menu-announcements' => 'Menu: Pengumuman',
            'menu-complaints' => 'Menu: Laporan Warga',
            'menu-forum' => 'Menu: Forum',
            'menu-users' => 'Menu: User',
            'menu-roles' => 'Menu: Role & Akses',
            'menu-activity-logs' => 'Menu: Log Aktivitas',
        ];

        foreach ($menuMap as $slug => $actionSlugs) {
            $perm = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $menuLabels[$slug] ?? $slug,
                'group' => 'menu',
            ]);
            $permIds[$slug] = $perm->id;
        }

        $map = [
            'super_admin' => array_keys($permIds),
            'admin' => array_keys($permIds),
            'finance' => ['view-dashboard', 'access-admin', 'manage-billing', 'manage-payment', 'verify-payment', 'manage-finance'],
            'rt' => ['view-dashboard', 'access-admin', 'manage-residents', 'manage-houses', 'manage-announcement', 'manage-event', 'manage-administration'],
            'rw' => ['view-dashboard', 'access-admin', 'manage-residents', 'manage-announcement', 'manage-event'],
            'security' => ['view-dashboard', 'access-admin', 'manage-visitor', 'manage-vehicle', 'manage-package', 'manage-emergency'],
            'maintenance' => ['view-dashboard', 'access-admin', 'manage-complaint', 'manage-facility', 'manage-booking'],
            'resident' => ['view-dashboard'],
        ];

        // Turunkan permission menu otomatis: role mendapat menu-... bila
        // memiliki minimal satu permission aksi yang membuka menu tsb.
        foreach ($map as $slug => $slugs) {
            foreach ($menuMap as $menuSlug => $actionSlugs) {
                if ($slug === 'super_admin' || $slug === 'admin' || array_intersect($actionSlugs, $slugs) !== []) {
                    $map[$slug][] = $menuSlug;
                }
            }
        }

        foreach ($map as $slug => $slugs) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $role->permissions()->sync(collect($slugs)->map(fn ($s) => $permIds[$s])->all());
            }
        }

        // Permission sisa dari fitur yang sudah dihapus (mis. modul Air/Token) dibuang
        // agar tidak lagi muncul di halaman Role & Akses.
        Permission::whereNotIn('slug', array_keys($permIds))
            ->get()
            ->each(function (Permission $permission) {
                $permission->roles()->detach();
                $permission->delete();
            });

        $superAdmin = Role::where('slug', 'super_admin')->first();
        User::firstOrCreate(['email' => 'admin@housinghub.id'], [
            'name' => 'Super Admin',
            'password' => Hash::make('password123'),
            'role_id' => $superAdmin?->id,
            'status' => 'active',
        ]);
    }
}
