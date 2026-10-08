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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            // Tamu (belum login) tetap dihitung, jadi user_id nullable.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 255);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Dashboard platform selalu menyaring per rentang tanggal dan
            // per path, jadi kedua indeks ini yang paling sering dipakai.
            $table->index(['created_at', 'user_id']);
            $table->index(['path', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
