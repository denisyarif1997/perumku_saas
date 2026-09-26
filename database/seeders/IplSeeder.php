<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\House;
use App\Models\IplRate;
use App\Models\Payment;
use App\Services\IplBillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IplSeeder extends Seeder
{
    /**
     * Data demo IPL: tarif bulanan + tagihan 3 periode terakhir.
     * Periode sebelum bulan berjalan ditandai lunas, periode berjalan dibiarkan belum bayar.
     */
    public function run(): void
    {
        $rate = IplRate::firstOrCreate(
            ['name' => 'IPL Bulanan Standar', 'housing_estate_id' => null],
            [
                'amount' => 150000,
                'period_type' => 'monthly',
                'effective_date' => Carbon::create(now()->year, 1, 1)->toDateString(),
                'end_date' => null,
                'description' => 'Iuran Pengelolaan Lingkungan bulanan untuk seluruh rumah.',
                'status' => 'active',
            ]
        );

        $service = app(IplBillingService::class);

        for ($offset = 2; $offset >= 0; $offset--) {
            $period = now()->copy()->startOfMonth()->subMonths($offset);

            $service->generate(
                (int) $period->year,
                (int) $period->month,
                null,
                10,
                null,
            );
        }

        // Tandai periode lampau sebagai lunas (settlement demo).
        $paidPeriods = [
            [now()->copy()->startOfMonth()->subMonths(2), 'transfer', 'BCA-2026-0001'],
            [now()->copy()->startOfMonth()->subMonths(1), 'qris', 'QRIS-2026-0002'],
        ];

        foreach ($paidPeriods as [$period, $method, $reference]) {
            $billings = Billing::query()
                ->forPeriod((int) $period->year, (int) $period->month)
                ->where('status', '!=', 'paid')
                ->get();

            foreach ($billings as $billing) {
                $amount = (float) $billing->total;

                Payment::firstOrCreate(
                    ['billing_id' => $billing->id, 'reference_number' => $reference.'-'.$billing->house_id],
                    [
                        'payment_number' => Payment::generateNumber($billing),
                        'resident_id' => $billing->resident_id,
                        'amount' => $amount,
                        'payment_date' => $period->copy()->day(8)->toDateString(),
                        'payment_method' => $method,
                        'status' => 'verified',
                        'verified_at' => $period->copy()->day(8)->endOfDay(),
                        'notes' => 'Data demo pelunasan IPL.',
                    ]
                );

                $billing->syncPaymentStatus();
            }
        }

        // Periode berjalan: biarkan 2 rumah sudah mengajukan pembayaran (pending verifikasi).
        $currentBillings = Billing::query()
            ->forPeriod((int) now()->year, (int) now()->month)
            ->whereIn('status', ['unpaid', 'partial'])
            ->with('house')
            ->orderBy('house_id')
            ->limit(2)
            ->get();

        foreach ($currentBillings as $billing) {
            Payment::firstOrCreate(
                ['billing_id' => $billing->id, 'status' => 'pending'],
                [
                    'payment_number' => Payment::generateNumber($billing),
                    'resident_id' => $billing->resident_id,
                    'user_id' => House::find($billing->house_id)?->houseResidents()->first()?->resident?->user?->id,
                    'amount' => (float) $billing->total,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'transfer',
                    'reference_number' => 'TRF-DEMO-'.$billing->house_id,
                    'status' => 'pending',
                    'notes' => 'Menunggu verifikasi pengelola.',
                ]
            );
        }
    }
}
