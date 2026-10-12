<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paket default SaaS Perumku
    |--------------------------------------------------------------------------
    | Semua angka bisa dioverride lewat .env agar harga bisa diubah tanpa
    | deploy ulang. Asumsi awal (mohon sesuaikan):
    | - Trial 15 hari sejak perumahan disetujui superadmin.
    | - Kuota 100 user aktif per perumahan termasuk dalam paket.
    | - Kelebihan user dikenai biaya tambahan per user per bulan.
    */
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 15),

    'included_users' => (int) env('SUBSCRIPTION_INCLUDED_USERS', 100),

    // Harga paket bulanan (Rupiah). Contoh default Rp149.000/bln.
    'monthly_price' => (int) env('SUBSCRIPTION_MONTHLY_PRICE', 149000),

    // Biaya tambahan per user kelebihan, per bulan (Rupiah). Contoh Rp1.500.
    'overage_per_user' => (int) env('SUBSCRIPTION_OVERAGE_PER_USER', 1500),

    // Gateway aktif: midtrans | xendit | manual (tanpa gateway, superadmin verifikasi manual).
    'gateway' => env('SUBSCRIPTION_GATEWAY', 'midtrans'),

    // Batas toleransi (grace period) setelah trial/tempo habis sebelum dikunci.
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 0),

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'success_url' => env('XENDIT_SUCCESS_URL'),
    ],
];
