<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto meteran disimpan sebagai file di disk public (storage/app/public/water-meters),
        // bukan BLOB di database. Kolom blob lama dibuang.
        Schema::table('water_meter_readings', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('notes');
        });

        Schema::table('water_meter_readings', function (Blueprint $table) {
            $table->dropColumn(['photo', 'photo_mime']);
        });
    }

    public function down(): void
    {
        Schema::table('water_meter_readings', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('water_meter_readings', function (Blueprint $table) {
            $table->binary('photo')->nullable();
            $table->string('photo_mime', 40)->nullable();
        });
    }
};
