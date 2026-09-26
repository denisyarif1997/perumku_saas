<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('water_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained('houses')->cascadeOnDelete();
            $table->string('meter_number')->unique();
            $table->string('serial_number')->nullable()->unique();
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['house_id', 'status']);
            $table->index('meter_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_meters');
    }
};
