<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pisah invoice IPL vs Air: billing air memakai water_rate_id.
     * IPL memakai ipl_rate_id. Dibuat dua unique agar 1 rumah boleh
     * punya 1 tagihan IPL + 1 tagihan Air pada periode yang sama.
     */
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->string('billing_type')->default('ipl')->after('ipl_rate_id');
            $table->foreignId('water_rate_id')->nullable()->after('ipl_rate_id')->constrained('water_rates')->nullOnDelete();
            $table->decimal('meter_start', 12, 2)->nullable()->after('water_rate_id');
            $table->decimal('meter_end', 12, 2)->nullable()->after('meter_start');
            $table->decimal('usage_m3', 12, 2)->nullable()->after('meter_end');
        });

        // SQLite lama (test) tidak mendukung dropUnique by name dengan cara sama,
        // jaga agar migrasi aman di MySQL (produksi) dan SQLite (test).
        try {
            Schema::table('billings', function (Blueprint $table) {
                $table->dropUnique('billings_house_period_rate_unique');
            });
        } catch (Throwable $e) {
            // abaikan bila index belum ada (misal database fresh tertentu)
        }

        Schema::table('billings', function (Blueprint $table) {
            $table->unique(['house_id', 'period_year', 'period_month', 'ipl_rate_id'], 'billings_house_period_ipl_unique');
            $table->unique(['house_id', 'period_year', 'period_month', 'water_rate_id'], 'billings_house_period_water_unique');
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            try {
                $table->dropUnique('billings_house_period_water_unique');
            } catch (Throwable $e) {
            }
            try {
                $table->dropUnique('billings_house_period_ipl_unique');
            } catch (Throwable $e) {
            }
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->unique(['house_id', 'period_year', 'period_month', 'ipl_rate_id'], 'billings_house_period_rate_unique');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('water_rate_id');
            $table->dropColumn(['billing_type', 'meter_start', 'meter_end', 'usage_m3']);
        });
    }
};
