<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\House;
use App\Models\IplRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IplBillingService
{
    /**
     * Generate tagihan IPL untuk rumah aktif pada satu periode.
     *
     * Mendukung multi-tarif per periode:
     * - Jika $rateId diisi: hanya tarif itu yang ditagihkan.
     * - Jika $houseIds diisi: hanya rumah itu yang ditagihkan (checklist warga).
     * - Anti-duplikat di level: rumah + tahun + bulan + tarif.
     *
     * @param  array<int>|null  $houseIds
     * @return array{created:int, restored:int, skipped:int, no_rate:int, rate_name:?string, total:float}
     */
    public function generate(int $year, int $month, ?int $estateId = null, int $dueDay = 10, ?int $userId = null, ?int $rateId = null, ?array $houseIds = null): array
    {
        $dueDay = min(28, max(1, $dueDay));
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dueDate = Carbon::create($year, $month, $dueDay)->toDateString();

        $result = [
            'created' => 0,
            'restored' => 0,
            'skipped' => 0,
            'no_rate' => 0,
            'rate_name' => null,
            'total' => 0.0,
        ];

        // Tarif paksa (dipilih manual di halaman Generate).
        $forcedRate = $rateId ? IplRate::find($rateId) : null;

        $houses = House::query()
            ->where('status', 'active')
            ->when($estateId, fn ($query) => $query->where('housing_estate_id', $estateId))
            ->when($houseIds !== null, fn ($query) => $query->whereIn('id', $houseIds))
            ->with(['houseResidents' => fn ($query) => $query
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->orderByDesc('is_owner')])
            ->get();

        DB::transaction(function () use ($houses, $year, $month, $periodStart, $dueDate, $userId, $forcedRate, &$result) {
            foreach ($houses as $house) {
                $rate = $forcedRate;

                // Tanpa tarif paksa: pakai tarif yang berlaku untuk estate rumah ini.
                // Jika tarif paksa milik estate lain, lewati rumah ini.
                if ($rate && $rate->housing_estate_id && (int) $rate->housing_estate_id !== (int) $house->housing_estate_id) {
                    $result['no_rate']++;

                    continue;
                }

                $rate ??= IplRate::forDate((int) $house->housing_estate_id, $periodStart);

                if (! $rate) {
                    $result['no_rate']++;

                    continue;
                }

                $existing = Billing::withTrashed()
                    ->where('house_id', $house->id)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->where('ipl_rate_id', $rate->id)
                    ->first();

                if ($existing && ! $existing->trashed()) {
                    $result['skipped']++;

                    continue;
                }

                $amount = (float) $rate->amount;

                $payload = [
                    'invoice_number' => $this->invoiceNumber($house, $year, $month, $rate->id),
                    'house_id' => $house->id,
                    'housing_estate_id' => $house->housing_estate_id,
                    'resident_id' => $house->houseResidents->first()?->resident_id,
                    'ipl_rate_id' => $rate->id,
                    'period_month' => $month,
                    'period_year' => $year,
                    'amount' => $amount,
                    'discount' => 0,
                    'total' => $amount,
                    'paid_amount' => 0,
                    'due_date' => $dueDate,
                    'status' => 'unpaid',
                    'created_by' => $userId,
                ];

                if ($existing) {
                    $existing->restore();
                    $existing->update($payload);
                    $result['restored']++;
                } else {
                    Billing::create($payload);
                    $result['created']++;
                }

                $result['rate_name'] = $rate->name;
                $result['total'] += $amount;
            }
        });

        return $result;
    }

    /**
     * Nomor invoice unik per rumah per periode per tarif.
     * Contoh: IPL/202609/0001/R02
     */
    public function invoiceNumber(House $house, int $year, int $month, ?int $rateId = null): string
    {
        $base = 'IPL/'
            .$year
            .str_pad((string) $month, 2, '0', STR_PAD_LEFT)
            .'/'
            .str_pad((string) $house->id, 4, '0', STR_PAD_LEFT);

        return $rateId ? $base.'/R'.str_pad((string) $rateId, 2, '0', STR_PAD_LEFT) : $base;
    }
}
