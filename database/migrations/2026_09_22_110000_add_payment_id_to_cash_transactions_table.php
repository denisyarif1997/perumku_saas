<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('destination_account_id')
                ->constrained('payments')->nullOnDelete();
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropIndex(['payment_id']);
            $table->dropColumn('payment_id');
        });
    }
};
