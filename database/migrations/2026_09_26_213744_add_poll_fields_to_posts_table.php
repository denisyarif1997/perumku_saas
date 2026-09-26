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
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_poll')->default(false)->after('is_pinned');
            $table->string('poll_type')->default('single')->after('is_poll');
            $table->json('poll_options')->nullable()->after('poll_type');
            $table->timestamp('poll_closes_at')->nullable()->after('poll_options');
            $table->boolean('poll_is_closed')->default(false)->after('poll_closes_at');

            $table->index(['is_poll', 'poll_closes_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['is_poll', 'poll_closes_at']);

            $table->dropColumn([
                'is_poll', 'poll_type', 'poll_options', 'poll_closes_at', 'poll_is_closed',
            ]);
        });
    }
};
