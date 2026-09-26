<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('housing_estate_id')
                ->nullable()
                ->after('resident_id')
                ->constrained('housing_estates')
                ->nullOnDelete();
            $table->index('housing_estate_id');
        });

        // Backfill 1: akun warga di-ikat ke estate rumah hunian aktifnya.
        // Portabel (loop PHP) agar aman di SQLite maupun MySQL.
        $unbound = DB::table('users')
            ->whereNull('housing_estate_id')
            ->whereNotNull('resident_id')
            ->get(['id', 'resident_id']);

        foreach ($unbound as $user) {
            $estateId = DB::table('house_residents')
                ->join('houses', 'houses.id', '=', 'house_residents.house_id')
                ->where('house_residents.resident_id', $user->resident_id)
                ->where('house_residents.status', 'active')
                ->orderByDesc('house_residents.is_primary')
                ->value('houses.housing_estate_id');

            if ($estateId !== null) {
                DB::table('users')->where('id', $user->id)->update(['housing_estate_id' => $estateId]);
            }
        }

        // Backfill 2: instalasi single-estate (aturan lama maks 1 perumahan) →
        // semua pengguna tersisa di-assign ke estate tunggal agar upgrade mulus.
        // Multi-estate: sisa pengguna null dianggap staf platform.
        $estateIds = DB::table('housing_estates')->pluck('id');
        if ($estateIds->count() === 1) {
            DB::table('users')
                ->whereNull('housing_estate_id')
                ->update(['housing_estate_id' => $estateIds->first()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('housing_estate_id');
        });
    }
};
