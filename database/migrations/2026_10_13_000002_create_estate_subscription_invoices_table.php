<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estate_subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->constrained('housing_estates')->cascadeOnDelete();
            $table->string('number')->unique();
            // base = langganan bulanan, overage = kelebihan user
            $table->string('type', 20)->default('base');
            $table->string('period_label')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedBigInteger('base_amount')->default(0);
            $table->unsignedInteger('included_users')->default(100);
            $table->unsignedInteger('billed_users')->default(0);
            $table->unsignedInteger('overage_users')->default(0);
            $table->unsignedBigInteger('overage_per_user')->default(0);
            $table->unsignedBigInteger('overage_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            // draft | pending | paid | failed | expired | cancelled
            $table->string('status', 20)->default('draft');
            $table->string('gateway', 20)->default('midtrans');
            $table->string('gateway_reference')->nullable();
            $table->string('payment_url')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('gateway_payload')->nullable();
            $table->timestamps();

            $table->index(['housing_estate_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estate_subscription_invoices');
    }
};
