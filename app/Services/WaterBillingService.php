<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\House;
use App\Models\WaterMeterReading;
use App\Models\WaterRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WaterBillingService
{
    /**
     * Simpan bacaan meter (status draft) tanpa membuat tagihan.
     * Bacaan yang sudah ditagih (billed) tidak diubah.
     *
     * @param  array<int, int|string|null>  $readings  [house_id => angka meter akhir]
     * @param  array<int, string>  $photos  [house_id => path foto di disk public]
     * @param  array<int, float>  $startOverrides  [house_id => meter awal manual (opsional)]
     * @return array{saved: int, skipped: int, no_rate: int, invalid: int, attached: int, photo_dropped: int}
     */
    public function saveReadings(int $year, int $month, ?int $estateId, ?int $userId, ?int $rateId, array $readings, array $photos = [], array $startOverrides = []): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $out = ['saved' => 0, 'skipped' => 0, 'no_rate' => 0, 'invalid' => 0, 'attached' => 0, 'photo_dropped' => 0];
        $forced = $rateId ? WaterRate::find($rateId) : null;
        $ids = array_keys($readings);
        $houses = House::query()->where('status', 'active')
            ->when($estateId, fn ($q) => $q->where('housing_estate_id', $estateId))
            ->whereIn('id', $ids)
            ->get();

        DB::transaction(function () use ($houses, $readings, $photos, $startOverrides, $year, $month, $periodStart, $userId, $forced, &$out) {
            foreach ($houses as $house) {
                $rate = $forced;
                if ($rate && $rate->housing_estate_id && (int) $rate->housing_estate_id !== (int) $house->housing_estate_id) {
                    $out['no_rate']++;

                    continue;
                }
                $rate ??= WaterRate::forDate((int) $house->housing_estate_id, $periodStart);
                if (! $rate) {
                    $out['no_rate']++;

                    continue;
                }
                $raw = $readings[$house->id] ?? null;
                if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                    $out['invalid']++;

                    continue;
                }
                $end = (float) $raw;
                $start = isset($startOverrides[$house->id])
                    ? (float) $startOverrides[$house->id]
                    : (float) ($this->lastEnd($house->id, $year, $month) ?? 0);
                if ($end < $start) {
                    $out['invalid']++;

                    continue;
                }
                $exist = WaterMeterReading::where('house_id', $house->id)
                    ->where('period_year', $year)->where('period_month', $month)->first();
                if ($exist && $exist->status === 'billed') {
                    $out['skipped']++;

                    continue;
                }
                $calc = WaterMeterReading::calculate($start, $end, (float) $rate->price_per_m3, (float) $rate->admin_fee, (float) $rate->min_usage_m3);
                $payload = [
                    'meter_start' => $start, 'meter_end' => $end, 'usage_m3' => $calc['usage'],
                    'water_rate_id' => $rate->id, 'price_per_m3' => $rate->price_per_m3,
                    'admin_fee' => $rate->admin_fee, 'amount' => $calc['total'], 'recorded_by' => $userId,
                ];
                if (isset($photos[$house->id])) {
                    if ($exist && $exist->photo_path && $exist->photo_path !== $photos[$house->id]) {
                        Storage::disk('public')->delete($exist->photo_path);
                    }
                    $payload['photo_path'] = $photos[$house->id];
                }
                WaterMeterReading::updateOrCreate(
                    ['house_id' => $house->id, 'period_year' => $year, 'period_month' => $month],
                    $payload + ['status' => 'draft', 'billing_id' => null]
                );
                $out['saved']++;
            }

            // Foto tanpa perubahan angka meter: lampirkan ke bacaan draft yang sudah ada.
            foreach ($photos as $hid => $path) {
                if (array_key_exists($hid, $readings)) {
                    continue;
                }
                $exist = WaterMeterReading::where('house_id', $hid)
                    ->where('period_year', $year)->where('period_month', $month)->first();
                if ($exist && $exist->status === 'draft') {
                    if ($exist->photo_path) {
                        Storage::disk('public')->delete($exist->photo_path);
                    }
                    $exist->update(['photo_path' => $path, 'recorded_by' => $userId]);
                    $out['attached']++;
                } else {
                    $out['photo_dropped']++;
                }
            }
        });

        return $out;
    }

    /**
     * Buat tagihan air dari seluruh bacaan berstatus draft pada satu periode.
     *
     * @return array{created: int, restored: int, no_rate: int, rate_name: string|null, total: float}
     */
    public function generateFromDrafts(int $year, int $month, ?int $estateId, int $dueDay, ?int $userId, ?int $rateId = null): array
    {
        $dueDay = min(28, max(1, $dueDay));
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dueDate = Carbon::create($year, $month, $dueDay)->toDateString();
        $out = ['created' => 0, 'restored' => 0, 'no_rate' => 0, 'rate_name' => null, 'total' => 0.0];
        $forced = $rateId ? WaterRate::find($rateId) : null;

        $readings = WaterMeterReading::query()
            ->where('period_year', $year)->where('period_month', $month)
            ->where('status', 'draft')
            ->with(['house' => fn ($q) => $q->with(['houseResidents' => fn ($qq) => $qq->where('status', 'active')->orderByDesc('is_primary')])])
            ->orderBy('house_id')
            ->get()
            ->filter(fn (WaterMeterReading $r) => $r->house !== null
                && $r->house->status === 'active'
                && ($estateId === null || (int) $r->house->housing_estate_id === $estateId));

        DB::transaction(function () use ($readings, $forced, $year, $month, $periodStart, $dueDate, $userId, &$out) {
            foreach ($readings as $reading) {
                $house = $reading->house;
                $rate = $forced;
                if ($rate && $rate->housing_estate_id && (int) $rate->housing_estate_id !== (int) $house->housing_estate_id) {
                    $out['no_rate']++;

                    continue;
                }
                $rate ??= WaterRate::forDate((int) $house->housing_estate_id, $periodStart);
                if (! $rate) {
                    $out['no_rate']++;

                    continue;
                }
                $start = (float) $reading->meter_start;
                $end = (float) $reading->meter_end;
                $calc = WaterMeterReading::calculate($start, $end, (float) $rate->price_per_m3, (float) $rate->admin_fee, (float) $rate->min_usage_m3);

                $exist = Billing::withTrashed()->where('house_id', $house->id)
                    ->where('period_year', $year)->where('period_month', $month)
                    ->where('billing_type', 'water')->first();
                if ($exist && ! $exist->trashed()) {
                    // Tagihan sudah ada — sinkronkan bacaan draft agar tidak menggandakan tagihan.
                    $reading->update(['water_rate_id' => $rate->id, 'price_per_m3' => $rate->price_per_m3,
                        'admin_fee' => $rate->admin_fee, 'amount' => $calc['total'],
                        'billing_id' => $exist->id, 'status' => 'billed']);

                    continue;
                }

                $data = ['invoice_number' => $this->inv($house, $year, $month), 'house_id' => $house->id,
                    'housing_estate_id' => $house->housing_estate_id,
                    'resident_id' => $house->houseResidents->first()?->resident_id, 'ipl_rate_id' => null,
                    'water_rate_id' => $rate->id, 'billing_type' => 'water', 'period_month' => $month, 'period_year' => $year,
                    'meter_start' => $start, 'meter_end' => $end, 'usage_m3' => $calc['usage'], 'amount' => $calc['water'],
                    'discount' => 0, 'total' => $calc['total'], 'paid_amount' => 0, 'due_date' => $dueDate, 'status' => 'unpaid',
                    'notes' => 'Air '.$start.'-'.$end.' m3 (pakai '.$calc['usage'].' m3)', 'created_by' => $userId];
                if ($exist) {
                    $exist->restore();
                    $exist->update($data);
                    $billing = $exist->fresh();
                    $out['restored']++;
                } else {
                    $billing = Billing::create($data);
                    $out['created']++;
                }
                $reading->update(['usage_m3' => $calc['usage'], 'water_rate_id' => $rate->id,
                    'price_per_m3' => $rate->price_per_m3, 'admin_fee' => $rate->admin_fee,
                    'amount' => $calc['total'], 'billing_id' => $billing->id, 'status' => 'billed']);
                $out['rate_name'] = $rate->name;
                $out['total'] += (float) $calc['total'];
            }
        });

        return $out;
    }

    /**
     * Simpan bacaan lalu langsung generate tagihan (perilaku lama yang tetap dipakai test).
     */
    public function recordAndGenerate(int $year, int $month, ?int $estateId, int $dueDay, ?int $userId, ?int $rateId, array $readings): array
    {
        $saved = $this->saveReadings($year, $month, $estateId, $userId, $rateId, $readings);
        $gen = $this->generateFromDrafts($year, $month, $estateId, $dueDay, $userId, $rateId);

        return [
            'created' => $gen['created'], 'restored' => $gen['restored'],
            'skipped' => $saved['skipped'],
            'no_rate' => $saved['no_rate'] + $gen['no_rate'],
            'invalid' => $saved['invalid'],
            'attached' => $saved['attached'], 'photo_dropped' => $saved['photo_dropped'],
            'rate_name' => $gen['rate_name'], 'total' => $gen['total'],
        ];
    }

    protected function lastEnd(int $houseId, int $year, int $month): ?float
    {
        $r = WaterMeterReading::query()->where('house_id', $houseId)
            ->where(fn ($q) => $q->where('period_year', '<', $year)
                ->orWhere(fn ($qq) => $qq->where('period_year', $year)->where('period_month', '<', $month)))
            ->orderByDesc('period_year')->orderByDesc('period_month')->first();

        return $r ? (float) $r->meter_end : null;
    }

    public function inv(House $house, int $year, int $month): string
    {
        $base = 'AIR/'.$year.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $house->id, 4, '0', STR_PAD_LEFT);
        $c = $base;
        $i = 1;
        while (Billing::withTrashed()->where('invoice_number', $c)->exists()) {
            $i++;
            $c = $base.'-'.$i;
        }

        return $c;
    }
}
