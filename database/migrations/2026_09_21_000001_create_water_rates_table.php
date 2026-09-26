<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->nullable()->constrained('housing_estates')->nullOnDelete();
            $table->string('name');
            $table->decimal('price_per_m3', 12, 2);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('min_usage_m3', 8, 2)->default(0);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['housing_estate_id', 'status']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_rates');
    }
};
