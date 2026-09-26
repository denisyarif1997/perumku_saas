<?php

namespace App\Livewire\Platform;

use App\Models\Billing;
use App\Models\Complaint;
use App\Models\House;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\Payment;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard level platform untuk super_admin.
 *
 * Berbeda dengan Dashboard admin yang ter-scope ke satu condominan, halaman ini
 * merangkum seluruh condominan yang terdaftar. Karena CurrentEstate::id()
 * bernilai null untuk super_admin, seluruh query di sini berjalan tanpa filter
 * tenant — itulah gunanya halaman ini.
 *
 * Statistik per condominan diambil lewat beberapa query agregat (bukan N+1),
 * lalu dirangkai di memori. Jumlah condominan masih sedikit, jadi ini jauh lebih
 * mudah dibaca daripada subquery bertingkat.
 */
class Dashboard extends Component
{
    #[Layout('layouts.admin', ['title' => 'Dashboard Super Admin'])]
    public function render()
    {
        // Route sudah dibatasi middleware role:super_admin. Penjaga di sini
        // sebagai lapis kedua, supaya aman bila komponen dipanggil langsung.
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $estates = HousingEstate::query()->orderBy('name')->get();

        $userCounts = User::query()
            ->selectRaw('housing_estate_id, COUNT(*) as c')
            ->whereNotNull('housing_estate_id')
            ->groupBy('housing_estate_id')
            ->pluck('c', 'housing_estate_id');

        $blockCounts = HousingBlock::query()
            ->selectRaw('housing_estate_id, COUNT(*) as c')
            ->groupBy('housing_estate_id')
            ->pluck('c', 'housing_estate_id');

        $houseCounts = House::query()
            ->selectRaw('housing_estate_id, COUNT(*) as c')
            ->groupBy('housing_estate_id')
            ->pluck('c', 'housing_estate_id');

        // Warga tidak punya housing_estate_id; hubungannya lewat rumah hunian.
        $residentCounts = DB::table('residents')
            ->join('house_residents', 'house_residents.resident_id', '=', 'residents.id')
            ->join('houses', 'houses.id', '=', 'house_residents.house_id')
            ->where('house_residents.status', 'active')
            ->groupBy('houses.housing_estate_id')
            ->selectRaw('houses.housing_estate_id as eid, COUNT(DISTINCT residents.id) as c')
            ->pluck('c', 'eid');

        $billingStats = DB::table('billings')
            ->whereNotNull('housing_estate_id')
            ->groupBy('housing_estate_id')
            ->selectRaw(<<<'SQL'
                housing_estate_id as eid,
                COUNT(*) as c,
                SUM(total) as billed,
                SUM(paid_amount) as paid,
                SUM(CASE WHEN status IN ('unpaid', 'partial') THEN total - paid_amount ELSE 0 END) as outstanding
            SQL)
            ->get()
            ->keyBy('eid');

        $rows = $estates->map(function (HousingEstate $estate) use ($userCounts, $blockCounts, $houseCounts, $residentCounts, $billingStats): array {
            $stat = $billingStats[$estate->id] ?? null;

            return [
                'estate' => $estate,
                'users' => (int) ($userCounts[$estate->id] ?? 0),
                'blocks' => (int) ($blockCounts[$estate->id] ?? 0),
                'houses' => (int) ($houseCounts[$estate->id] ?? 0),
                'residents' => (int) ($residentCounts[$estate->id] ?? 0),
                'billings' => (int) ($stat->c ?? 0),
                'billed' => (float) ($stat->billed ?? 0),
                'paid' => (float) ($stat->paid ?? 0),
                'outstanding' => max(0, (float) ($stat->outstanding ?? 0)),
            ];
        })->values();

        $totals = [
            'estates' => $rows->count(),
            'estatesActive' => $estates->where('status', 'active')->count(),
            'users' => (int) $userCounts->sum(),
            'blocks' => (int) $blockCounts->sum(),
            'houses' => (int) $houseCounts->sum(),
            'residents' => (int) $residentCounts->sum(),
            'billings' => (int) $billingStats->sum('c'),
            'billed' => (float) $billingStats->sum('billed'),
            'paid' => (float) $billingStats->sum('paid'),
            'outstanding' => max(0, (float) $billingStats->sum('outstanding')),
        ];

        $totals['collectionRate'] = $totals['billed'] > 0
            ? round(($totals['paid'] / $totals['billed']) * 100, 1)
            : 0.0;

        return view('livewire.platform.dashboard', [
            'rows' => $rows,
            'totals' => $totals,
            'trend' => $this->monthlyTrend(),
            'topByResidents' => $rows->sortByDesc('residents')->take(5)->values(),
            'topByBilled' => $rows->sortByDesc('billed')->take(5)->values(),
            'recentEstates' => HousingEstate::query()->latest('id')->take(5)->get(),

            'activeUsers' => User::query()->where('status', 'active')->count(),
            'inactiveUsers' => User::query()->where('status', '!=', 'active')->count(),
            'pendingPayments' => Payment::query()->where('status', 'pending')->count(),
            'pendingPaymentsAmount' => (float) Payment::query()->where('status', 'pending')->sum('amount'),
            'openComplaints' => Complaint::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'newEstatesThisMonth' => HousingEstate::query()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ]);
    }

    /**
     * Enam bulan terakhir: jumlah tagihan dan nilainya per bulan.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function monthlyTrend(): array
    {
        $start = now()->subMonths(5)->startOfMonth();

        $raw = Billing::query()
            ->selectRaw('period_year as y, period_month as m, COUNT(*) as c, SUM(total) as billed, SUM(paid_amount) as paid')
            ->where('period_year', '>=', $start->year)
            ->groupBy('period_year', 'period_month')
            ->get()
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);

        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $row = $raw[$date->year.'-'.$date->month] ?? null;

            $series[] = [
                'label' => Currency::period($date->year, $date->month),
                'short' => mb_substr(Currency::monthName($date->month), 0, 3),
                'year' => $date->year,
                'count' => (int) ($row->c ?? 0),
                'billed' => (float) ($row->billed ?? 0),
                'paid' => (float) ($row->paid ?? 0),
            ];
        }

        return $series;
    }
}
