<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpan bukti bayar sebagai BLOB di database (maks 2 MB, gambar).
     * MEDIUMBLOB (MySQL, s.d. 16 MB) dipakai agar muat 2 MB.
     * Kolom `proof` lama (path storage) dipertahankan untuk kompatibilitas.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'proof_mime')) {
                $table->string('proof_mime', 100)->nullable()->after('proof');
            }
            if (! Schema::hasColumn('payments', 'proof_name')) {
                $table->string('proof_name', 255)->nullable()->after('proof_mime');
            }
            if (! Schema::hasColumn('payments', 'proof_size')) {
                $table->unsignedInteger('proof_size')->nullable()->after('proof_name');
            }
        });

        // Laravel tidak punya mediumBlob(), jadi pakai statement mentah per driver.
        if (! Schema::hasColumn('payments', 'proof_blob')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `payments` ADD COLUMN `proof_blob` MEDIUMBLOB NULL AFTER `proof_size`');
            } else {
                Schema::table('payments', function (Blueprint $table) {
                    $table->binary('proof_blob')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['proof_blob', 'proof_size', 'proof_name', 'proof_mime'] as $col) {
                if (Schema::hasColumn('payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
