<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staging pengajuan perumahan baru. Data di sini BELUM masuk ke tabel
     * produksi (housing_estates/users/residents) sampai superadmin approve.
     */
    public function up(): void
    {
        Schema::create('estate_registrations', function (Blueprint $table) {
            $table->id();
            // Pemohon (dibuat saat signup, status pending).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Jejak login Google bila daftar via Google.
            $table->foreignId('bridge_id')->nullable()->constrained('oauth_accounts')->nullOnDelete();
            $table->string('method')->default('email'); // email|google
            // Data diri calon admin.
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 15);
            $table->string('nik', 20)->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            // Data perumahan yang diajukan.
            $table->string('estate_code', 20);
            $table->string('estate_name', 100);
            $table->text('estate_address')->nullable();
            $table->string('estate_phone', 15)->nullable();
            $table->string('estate_email', 150)->nullable();
            // Data rumah pertama (opsional, untuk mengikat warga).
            $table->string('block_code', 20)->nullable();
            $table->string('block_name', 100)->nullable();
            $table->string('house_number', 50)->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->text('reject_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estate_registrations');
    }
};
