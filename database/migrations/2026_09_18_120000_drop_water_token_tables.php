<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus fitur Air — Token: drop tabel water token.
     */
    public function up(): void
    {
        Schema::dropIfExists('water_token_purchases');
        Schema::dropIfExists('water_token_products');
        Schema::dropIfExists('water_meters');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
