<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Perumahan lama yang belum punya langganan langsung masuk trial
        // 15 hari sejak migrasi ini dijalankan (portabel SQLite/MySQL).
        $trialDays = (int) env('SUBSCRIPTION_TRIAL_DAYS', 15);
        $included = (int) env('SUBSCRIPTION_INCLUDED_USERS', 100);
        $now = now();
        $ends = $now->copy()->addDays($trialDays);

        $ids = DB::table('housing_estates')->pluck('id');

        foreach ($ids as $id) {
            $exists = DB::table('estate_subscriptions')->where('housing_estate_id', $id)->exists();

            if ($exists) {
                continue;
            }

            DB::table('estate_subscriptions')->insert([
                'housing_estate_id' => $id,
                'status' => 'trial',
                'trial_started_at' => $now,
                'trial_ends_at' => $ends,
                'current_period_start' => $now,
                'current_period_end' => $ends,
                'included_users' => $included,
                'gateway' => env('SUBSCRIPTION_GATEWAY', 'midtrans'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Backfill tidak dihapus otomatis agar riwayat trial tidak hilang.
    }
};
