<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estate_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->unique()->constrained('housing_estates')->cascadeOnDelete();
            // trial | active | past_due | suspended | cancelled
            $table->string('status', 20)->default('trial');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->unsignedInteger('included_users')->default(100);
            $table->string('gateway', 20)->default('midtrans');
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('trial_ends_at');
            $table->index('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estate_subscriptions');
    }
};
