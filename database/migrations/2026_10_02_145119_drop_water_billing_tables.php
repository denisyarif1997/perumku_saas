<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus fitur Air sepenuhnya: bacaan meter, tarif air, dan tagihan air.
     *
     * Modular dihapus dari app (model, service, halaman, menu), jadi sisi
     * database ikut dibersihkan agar tidak ada sisa kolom/tabel yang yatim.
     *
     * Kolom & index billings yang dibangun khusus untuk Air (lihat migrasi
     * add_water_fields_to_billings_table) dikembalikan ke bentuk sebelum
     * Air ada: satu unique per (rumah, periode, tarif IPL).
     */
    public function up(): void
    {
        if (Schema::hasColumn('billings', 'billing_type')) {
            $waterBillingIds = DB::table('billings')
                ->where('billing_type', 'water')
                ->pluck('id');

            if ($waterBillingIds->isNotEmpty()) {
                // Pembayaran menunjuk billings dengan cascadeOnDelete, jadi ikut terhapus.
                // Transaksi kas yang payment_id-nya menunjuk pembayaran tersebut
                // hanya di-nullOnDelete — hapus eksplisit supaya tidak ada transaksi
                // kas yatim dari tagihan air yang sudah dibuang.
                if (Schema::hasTable('cash_transactions') && Schema::hasColumn('cash_transactions', 'payment_id')) {
                    DB::table('cash_transactions')
                        ->whereIn('payment_id', DB::table('payments')->whereIn('billing_id', $waterBillingIds)->select('id'))
                        ->delete();
                }

                // Soft-deleted tagihan air ikut dibuang agar tidak jadi sampah.
                DB::table('billings')->whereIn('id', $waterBillingIds)->delete();
            }
        }

        // Buang FK billings.water_rate_id lebih dulu: MySQL menolak drop tabel
        // water_rates selama masih ada kolom yang mereferensikannya.
        if (Schema::hasColumn('billings', 'water_rate_id')) {
            Schema::table('billings', function (Blueprint $table) {
                $table->dropForeign(['water_rate_id']);
            });
        }

        Schema::dropIfExists('water_meter_readings');
        Schema::dropIfExists('water_rates');

        if (Schema::hasColumn('billings', 'water_rate_id')) {
            Schema::table('billings', function (Blueprint $table) {
                // Kembalikan unique single-rate seperti sebelum fitur Air.
                $table->dropUnique('billings_house_period_water_unique');
                $table->dropUnique('billings_house_period_ipl_unique');

                $table->dropColumn(['billing_type', 'water_rate_id', 'meter_start', 'meter_end', 'usage_m3']);
            });

            Schema::table('billings', function (Blueprint $table) {
                $table->unique(['house_id', 'period_year', 'period_month', 'ipl_rate_id'], 'billings_house_period_rate_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('billings', 'billing_type')) {
            Schema::table('billings', function (Blueprint $table) {
                $table->dropUnique('billings_house_period_rate_unique');
            });

            Schema::table('billings', function (Blueprint $table) {
                $table->string('billing_type')->default('ipl')->after('ipl_rate_id');
                $table->decimal('meter_start', 12, 2)->nullable();
                $table->decimal('meter_end', 12, 2)->nullable();
                $table->decimal('usage_m3', 12, 2)->nullable();
            });
        }
    }
};
