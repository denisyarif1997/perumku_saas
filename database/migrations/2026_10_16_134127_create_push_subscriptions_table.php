<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Perbaiki sisa migrasi gagal sebelumnya (tabel ada tapi kolom belum lengkap).
        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('endpoint');
                $table->char('endpoint_hash', 64)->unique();
                $table->string('p256dh', 255);
                $table->string('auth', 255);
                $table->string('user_agent', 512)->nullable();
                $table->timestamps();

                $table->index('user_id');
            });

            return;
        }

        Schema::table('push_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('push_subscriptions', 'user_id')) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }

            if (Schema::hasColumn('push_subscriptions', 'endpoint')) {
                $table->text('endpoint')->change();
            } else {
                $table->text('endpoint');
            }

            if (! Schema::hasColumn('push_subscriptions', 'endpoint_hash')) {
                $table->char('endpoint_hash', 64)->unique();
            }

            if (! Schema::hasColumn('push_subscriptions', 'p256dh')) {
                $table->string('p256dh', 255);
            }

            if (! Schema::hasColumn('push_subscriptions', 'auth')) {
                $table->string('auth', 255);
            }

            if (! Schema::hasColumn('push_subscriptions', 'user_agent')) {
                $table->string('user_agent', 512)->nullable();
            }
        });

        // Isi hash untuk baris lama yang mungkin sudah ada.
        DB::table('push_subscriptions')->whereNull('endpoint_hash')->orWhere('endpoint_hash', '')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('push_subscriptions')->where('id', $row->id)->update([
                    'endpoint_hash' => hash('sha256', (string) $row->endpoint.$row->id),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
