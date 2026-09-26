<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housing_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housing_estate_id')->constrained('housing_estates')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['housing_estate_id', 'code']);
            $table->index(['housing_estate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housing_blocks');
    }
};
