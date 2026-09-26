<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->constrained('housing_estates')->cascadeOnDelete();
            $table->foreignId('housing_block_id')->constrained('housing_blocks')->cascadeOnDelete();
            $table->string('house_number');
            $table->text('address')->nullable();
            $table->decimal('land_area', 10, 2)->nullable();
            $table->decimal('building_area', 10, 2)->nullable();
            $table->string('ownership_status')->default('owner');
            $table->string('occupancy_status')->default('occupied');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['housing_block_id', 'house_number']);
            $table->index(['housing_block_id', 'house_number']);
            $table->index(['housing_estate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('houses');
    }
};
