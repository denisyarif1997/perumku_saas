<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            if (! Schema::hasColumn('page_views', 'housing_estate_id')) {
                $table->foreignId('housing_estate_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('housing_estates')
                    ->nullOnDelete();
                $table->index(['housing_estate_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            if (Schema::hasColumn('page_views', 'housing_estate_id')) {
                $table->dropConstrainedForeignId('housing_estate_id');
            }
        });
    }
};
