<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menormalisasi kolom housing_estate_id ke tabel billings & payments.
 *
 * Sebelumnya kedua tabel ini tidak punya kolom sendiri, sehingga isolasi tenant
 * harus lewat whereHas() ke house — sebuah subquery korelasi di setiap query,
 * termasuk pada jalur uang yang paling sering diakses (dashboard & daftar tagihan).
 *
 * Kolom didenormalisasi lalu di-backfill dari relasi yang sudah ada (house untuk
 * tagihan, tagihan untuk pembayaran), sehingga hasilnya konsisten dengan relasi
 * house. Setelah ini applyEstateScope() cukup menyaring satu kolom berindex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->foreignId('housing_estate_id')->nullable()->after('house_id')
                ->constrained('housing_estates')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('housing_estate_id')->nullable()->after('billing_id')
                ->constrained('housing_estates')->cascadeOnDelete();
        });

        $this->backfill();

        Schema::table('billings', function (Blueprint $table) {
            $table->index(['housing_estate_id', 'status'], 'billings_estate_status_index');
            $table->index(['housing_estate_id', 'period_year', 'period_month'], 'billings_estate_period_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['housing_estate_id', 'status'], 'payments_estate_status_index');
        });
    }

    /**
     * Isi kolom estate dari relasi yang sudah ada.
     */
    protected function backfill(): void
    {
        DB::table('billings')
            ->whereNull('housing_estate_id')
            ->update([
                'housing_estate_id' => DB::raw(
                    '(SELECT housing_estate_id FROM houses WHERE houses.id = billings.house_id)'
                ),
            ]);

        DB::table('payments')
            ->whereNull('housing_estate_id')
            ->update([
                'housing_estate_id' => DB::raw(
                    '(SELECT housing_estate_id FROM billings WHERE billings.id = payments.billing_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_estate_status_index');
            $table->dropConstrainedForeignId('housing_estate_id');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->dropIndex('billings_estate_period_index');
            $table->dropIndex('billings_estate_status_index');
            $table->dropConstrainedForeignId('housing_estate_id');
        });
    }
};
