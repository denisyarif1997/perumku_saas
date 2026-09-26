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
        Schema::create('water_token_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('nominal', 12, 2);
            $table->decimal('water_volume', 10, 2);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_token_products');
    }
};
