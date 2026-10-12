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
        Schema::create('resident_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->constrained('housing_estates')->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('house_id')->nullable()->constrained('houses')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            // Nama + kontak tujuan undangan (WA/email opsional, tanpa SMTP).
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            // Token publik di URL undangan (disimpan hash seperti password).
            $table->string('token_hash')->unique();
            // pending | accepted | revoked | expired (expired dihitung, bukan status simpan).
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['housing_estate_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_invitations');
    }
};
