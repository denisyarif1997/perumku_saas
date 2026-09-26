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
        Schema::create('water_token_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('house_id')->constrained('houses')->cascadeOnDelete();
            $table->foreignId('water_meter_id')->nullable()->constrained('water_meters')->nullOnDelete();
            $table->foreignId('water_token_product_id')->constrained('water_token_products')->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 32)->nullable();
            $table->decimal('nominal', 12, 2);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('water_volume', 10, 2);
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->default('cash');
            $table->string('proof')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['house_id', 'status']);
            $table->index('transaction_number');
            $table->index('status');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_token_purchases');
    }
};
