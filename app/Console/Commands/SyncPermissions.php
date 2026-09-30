<?php

namespace App\Console\Commands;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronkan role, permission aksi, dan permission menu ke database.
 *
 * Permission tidak dibuat lewat migration, melainkan lewat RolePermissionSeeder.
 * Minhala setiap ada modul baru, `php artisan migrate` saja tidak cukup —
 * menu baru tidak akan muncul di halaman Role & Akses sampai perintah ini
 * dijalankan. Seeder-nya idempoten, jadi aman dipanggil berulang setelah deploy.
 */
#[Signature('app:sync-permissions {--dry-run : Tampilkan daftar permission tanpa menulis ke database}')]
#[Description('Sinkronkan role, permission aksi, dan permission menu (aman dijalankan berulang)')]
class SyncPermissions extends Command
{
    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->components->info('Mode dry-run: tidak ada perubahan ke database.');

            return self::SUCCESS;
        }

        $before = $this->permissionCount();

        $this->components->info('Menjalankan RolePermissionSeeder...');
        $this->call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
        ]);

        $after = $this->permissionCount();

        $this->components->info("Permission di database: {$before} → {$after}");
        $this->components->twoColumnDetail('Menu inventaris', $this->inventoryStatus());

        $this->newLine();
        $this->components->info('Selesai. Buka Sistem → Role & Akses → Kelola Akses untuk melihat menu baru.');

        return self::SUCCESS;
    }

    protected function permissionCount(): int
    {
        return DB::table('permissions')->count();
    }

    /**
     * Ringkasan permission inventaris, untuk memastikan modul yang baru
     * ditambahkan benar-benar sudah terdaftar.
     */
    protected function inventoryStatus(): string
    {
        $slugs = DB::table('permissions')
            ->where('slug', 'like', '%inventory%')
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        return $slugs === [] ? 'belum terdaftar' : implode(', ', $slugs);
    }
}
