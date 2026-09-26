<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('resident_id')->nullable()->after('id')->constrained('residents')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('resident_id')->constrained('roles')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('status')->default('active')->after('avatar');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resident_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['phone', 'avatar', 'status', 'last_login_at', 'deleted_at']);
        });
    }
};
