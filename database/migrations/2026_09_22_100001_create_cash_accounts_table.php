<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->nullable()->constrained('housing_estates')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('cash'); // cash | bank
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['housing_estate_id', 'status']);
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->cascadeOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('type'); // in | out | transfer
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');
            $table->string('category')->nullable(); // contoh: ipl, donation, operational, transfer
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cash_account_id', 'transaction_date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_accounts');
    }
};
