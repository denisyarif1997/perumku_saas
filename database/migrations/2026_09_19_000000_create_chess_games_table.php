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
        Schema::create('chess_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('white_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('black_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Pembuat tantangan (menunggu lawan).
            $table->foreignId('inviter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invitee_user_id')->nullable()->constrained('users')->nullOnDelete();
            // pending | active | finished | declined | cancelled
            $table->string('status')->default('pending')->index();
            // w | b | draw (pemenang)
            $table->string('winner', 10)->nullable();
            // checkmate | stalemate | resign | fifty_move | agreement
            $table->string('end_reason')->nullable();
            // State papan: board (JSON 64 kotak), turn, castling, en_passant, halfmove, fullmove
            $table->json('board');
            $table->string('turn', 1)->default('w');
            $table->string('castling', 4)->default('KQkq');
            $table->unsignedTinyInteger('en_passant')->nullable();
            $table->unsignedSmallInteger('halfmove')->default(0);
            $table->unsignedSmallInteger('fullmove')->default(1);
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chess_games');
    }
};
