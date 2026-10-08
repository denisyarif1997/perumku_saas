<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename ms_bridgeing (typo + prefix menyesatkan) ke oauth_accounts.
     * Aman diulang: cek keberadaan tabel dulu. FK estate_registrations.bridge_id
     * ikut di-rename agar tetap valid.
     */
    public function up(): void
    {
        if (Schema::hasTable('ms_bridgeing') && ! Schema::hasTable('oauth_accounts')) {
            Schema::rename('ms_bridgeing', 'oauth_accounts');
        }

        if (Schema::hasTable('estate_registrations') && Schema::hasTable('oauth_accounts')) {
            try {
                Schema::table('estate_registrations', function ($table) {
                    $table->dropForeign(['bridge_id']);
                });
            } catch (Throwable) {
                // FK mungkin sudah bernama lain / sudah dilepas.
            }

            try {
                Schema::table('estate_registrations', function ($table) {
                    $table->foreign('bridge_id')->references('id')->on('oauth_accounts')->nullOnDelete();
                });
            } catch (Throwable) {
                // FK sudah menunjuk ke oauth_accounts.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('oauth_accounts') && ! Schema::hasTable('ms_bridgeing')) {
            Schema::rename('oauth_accounts', 'ms_bridgeing');
        }
    }
};
