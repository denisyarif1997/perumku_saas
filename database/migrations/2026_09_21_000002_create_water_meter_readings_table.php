<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained('houses')->cascadeOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->decimal('meter_start', 12, 2)->default(0);
            $table->decimal('meter_end', 12, 2);
            $table->decimal('usage_m3', 12, 2);
            $table->foreignId('water_rate_id')->nullable()->constrained('water_rates')->nullOnDelete();
            $table->decimal('price_per_m3', 12, 2);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->foreignId('billing_id')->nullable()->constrained('billings')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['house_id', 'period_year', 'period_month'], 'water_readings_house_period_unique');
            $table->index(['period_year', 'period_month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_meter_readings');
    }
};
