<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_meter_readings', function (Blueprint $table) {
            $table->binary('photo')->nullable();
            $table->string('photo_mime', 40)->nullable();
        });

        // BLOB MySQL hanya 64KB; pakai MEDIUMBLOB agar muat foto hasil kompresi (<= 1MB).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `water_meter_readings` MODIFY `photo` MEDIUMBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('water_meter_readings', function (Blueprint $table) {
            $table->dropColumn(['photo', 'photo_mime']);
        });
    }
};
