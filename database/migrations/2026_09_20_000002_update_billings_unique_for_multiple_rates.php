<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Izinkan satu rumah punya >1 tagihan per periode (satu per tarif).
     * Sebelumnya unik: house + tahun + bulan (hanya 1 tarif per periode).
     * Sesudahnya unik: house + tahun + bulan + tarif.
     */
    public function up(): void
    {
        // Buat index pengganti DULU, baru hapus yang lama.
        // (MySQL menolak drop index yang masih dipakai foreign key.)
        Schema::table('billings', function (Blueprint $table) {
            $table->index('house_id', 'billings_house_id_index');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->dropUnique('billings_house_period_unique');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->unique(
                ['house_id', 'period_year', 'period_month', 'ipl_rate_id'],
                'billings_house_period_rate_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropUnique('billings_house_period_rate_unique');
            $table->dropIndex('billings_house_id_index');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->unique(
                ['house_id', 'period_year', 'period_month'],
                'billings_house_period_unique'
            );
        });
    }
};
