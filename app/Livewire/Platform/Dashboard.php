<?php

namespace App\Livewire\Platform;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Billing;
use App\Models\ChessGame;
use App\Models\Complaint;
use App\Models\HousingEstate;
use App\Models\ItemLoan;
use App\Models\PageView;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Resident;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard level platform untuk super_admin.
 *
 * Berbeda dengan Dashboard admin yang ter-scope ke satu condominan, halaman ini
 * merangkum pemakaian aplikasi di seluruh condominan. Karena CurrentEstate::id()
 * bernilai null untuk super_admin, seluruh query di sini berjalan tanpa filter
 * tenant — itulah gunanya halaman ini.
 *
 * Fokusnya adalah "seberapa sering, oleh siapa, dan bagian mana yang dipakai":
 * kunjungan halaman, pengunjung unik, fitur yang paling banyak dipakai, dan
 * modul yang paling aktif. Ringkasan tagihan tetap ada sebagai konteks
 * platform, bukan sebagai fokus halaman.
 *
 * Semua agregat diambil lewat query groupBy (bukan N+1), lalu dirangkai di
 * memori. Jumlah condominan masih sedikit, jadi ini jauh lebih mudah dibaca
 * daripada subquery bertingkat.
 */
class Dashboard extends Component
{
    /** Panjang grafik tren kunjungan. */
    protected const TREND_DAYS = 14;

    /** Jendela hitung untuk seluruh angka pemakaian (30 hari). */
    protected const WINDOW_DAYS = 30;

    #[Layout('layouts.admin', ['title' => 'Dashboard Platform'])]
    public function render()
    {
        // Route sudah dibatasi middleware role:super_admin. Penjaga di sini
        // sebagai lapis kedua, supaya aman bila komponen dipanggil langsung.
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $since = now()->subDays(self::WINDOW_DAYS - 1)->startOfDay();

        return view('livewire.platform.dashboard', [
            'windowDays' => self::WINDOW_DAYS,
            'visits' => $this->visitSummary($since),
            'trend' => $this->visitTrend(),
            'platform' => $this->platformSummary(),
            'features' => $this->featureUsage($since),
            'modules' => $this->moduleUsage($since),
            'estateRows' => $this->estateUsage($since),
            'topPages' => $this->topPages($since),
            'topUsers' => $this->topUsers($since),
            'devices' => $this->deviceShare($since),
            'recentActivity' => ActivityLog::with('user')->latest('id')->take(8)->get(),
            'attention' => $this->needsAttention(),
        ]);
    }

    /**
     * Angka utama pemakaian: kunjungan, pengunjung unik, dan rata-rata harian.
     *
     * @return array<string, float|int>
     */
    protected function visitSummary(Carbon $since): array
    {
        $recent = PageView::query()->since($since);

        $total = (clone $recent)->count();

        return [
            'total' => $total,
            'today' => PageView::query()->whereDate('created_at', now()->toDateString())->count(),
            'uniqueVisitors' => (clone $recent)->whereNotNull('user_id')->distinct()->count('user_id'),
            'guestVisits' => (clone $recent)->whereNull('user_id')->count(),
            'perDay' => $total / self::WINDOW_DAYS,
            'allTime' => PageView::query()->count(),
        ];
    }

    /**
     * Tren kunjungan harian: total kunjungan dan pengunjung unik per hari.
     *
     * @return array<int, array{label: string, short: string, total: int, visitors: int}>
     */
    protected function visitTrend(): array
    {
        $start = now()->subDays(self::TREND_DAYS - 1)->startOfDay();

        $rows = PageView::query()
            ->since($start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, COUNT(DISTINCT user_id) as visitors')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];

        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $row = $rows[$date->toDateString()] ?? null;

            $series[] = [
                'label' => $date->format('d M'),
                'short' => $date->format('d'),
                'total' => (int) ($row->total ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Potret umum platform: condominan, pengguna, warga, dan ringkasan tagihan.
     *
     * @return array<string, float|int>
     */
    protected function platformSummary(): array
    {
        $billings = DB::table('billings')->whereNotNull('housing_estate_id');

        $billed = (float) $billings->sum('total');
        $paid = (float) $billings->sum('paid_amount');

        return [
            'estates' => HousingEstate::query()->count(),
            'estatesActive' => HousingEstate::query()->where('status', 'active')->count(),
            'newEstatesThisMonth' => HousingEstate::query()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'users' => User::query()->count(),
            'usersActive' => User::query()->where('status', 'active')->count(),
            'activeThisWeek' => User::query()
                ->where('status', 'active')
                ->where('last_login_at', '>=', now()->subDays(6)->startOfDay())
                ->count(),
            'newUsersThisMonth' => User::query()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'neverLoggedIn' => User::query()->whereNull('last_login_at')->count(),
            'residents' => Resident::query()->count(),
            'billed' => $billed,
            'paid' => $paid,
            'outstanding' => max(0, $billed - $paid),
            'collectionRate' => $billed > 0 ? round(($paid / $billed) * 100, 1) : 0.0,
        ];
    }

    /**
     * Seberapa sering tiap fitur dipakai dalam jendela 30 hari terakhir,
     * disandingkan dengan total sepanjang waktu.
     *
     * @return array<int, array{label: string, icon: string, period: int, total: int}>
     */
    protected function featureUsage(Carbon $since): array
    {
        $features = [
            ['label' => 'Postingan Forum', 'icon' => 'messages-square', 'model' => Post::class],
            ['label' => 'Komentar Forum', 'icon' => 'message-square', 'model' => PostComment::class],
            ['label' => 'Pengaduan', 'icon' => 'message-square-warning', 'model' => Complaint::class],
            ['label' => 'Pengumuman', 'icon' => 'megaphone', 'model' => Announcement::class],
            ['label' => 'Pembayaran', 'icon' => 'receipt', 'model' => Payment::class],
            ['label' => 'Tagihan Iuran', 'icon' => 'file-text', 'model' => Billing::class],
            ['label' => 'Peminjaman Barang', 'icon' => 'repeat', 'model' => ItemLoan::class],
            ['label' => 'Permainan Catur', 'icon' => 'grid-2x2', 'model' => ChessGame::class],
        ];

        $usage = [];

        foreach ($features as $feature) {
            $model = $feature['model'];

            $usage[] = [
                'label' => $feature['label'],
                'icon' => $feature['icon'],
                'period' => $model::query()->since($since)->count(),
                'total' => $model::query()->count(),
            ];
        }

        return $usage;
    }

    /**
     * Modul mana yang paling banyak dicatat di activity log, sebagai gambaran
     * kasar bagian aplikasi mana yang paling sering dikerjakan pengelola.
     *
     * @return array<int, array{label: string, total: int, share: float}>
     */
    protected function moduleUsage(Carbon $since): array
    {
        $rows = ActivityLog::query()
            ->since($since)
            ->whereNotNull('module')
            ->selectRaw('module, COUNT(*) as total')
            ->groupBy('module')
            ->orderByDesc('total')
            ->take(8)
            ->get();

        $max = (int) ($rows->max('total') ?? 0);

        return $rows->map(fn ($row) => [
            'label' => ActivityLog::moduleLabel($row->module),
            'total' => (int) $row->total,
            'share' => $max > 0 ? round(((int) $row->total / $max) * 100) : 0,
        ])->values()->all();
    }

    /**
     * Pemakaian per condominan: kunjungan, pengunjung unik, pengguna, warga,
     * dan kunjungan terakhirnya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function estateUsage(Carbon $since)
    {
        $visits = DB::table('page_views')
            ->where('created_at', '>=', $since)
            ->whereNotNull('housing_estate_id')
            ->groupBy('housing_estate_id')
            ->selectRaw('housing_estate_id as eid, COUNT(*) as total, COUNT(DISTINCT user_id) as visitors, MAX(created_at) as last_visit')
            ->get()
            ->keyBy('eid');

        $userCounts = User::query()
            ->whereNotNull('housing_estate_id')
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

        return HousingEstate::query()->orderBy('name')->get()->map(function (HousingEstate $estate) use ($visits, $userCounts, $residentCounts): array {
            $visit = $visits[$estate->id] ?? null;

            return [
                'estate' => $estate,
                'visits' => (int) ($visit->total ?? 0),
                'visitors' => (int) ($visit->visitors ?? 0),
                'lastVisit' => $visit->last_visit ?? null,
                'users' => (int) ($userCounts[$estate->id] ?? 0),
                'residents' => (int) ($residentCounts[$estate->id] ?? 0),
            ];
        });
    }

    /**
     * Halaman yang paling sering dibuka pengguna.
     *
     * @return Collection<int, array{path: string, total: int, visitors: int}>
     */
    protected function topPages(Carbon $since)
    {
        return PageView::query()
            ->since($since)
            ->groupBy('path')
            ->selectRaw('path, COUNT(*) as total, COUNT(DISTINCT user_id) as visitors')
            ->orderByDesc('total')
            ->take(8)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'total' => (int) $row->total,
                'visitors' => (int) $row->visitors,
            ])
            ->values();
    }

    /**
     * Pengguna yang paling aktif membuka aplikasi dalam jendela 30 hari.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function topUsers(Carbon $since)
    {
        $rows = PageView::query()
            ->since($since)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as total, MAX(created_at) as last_visit')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->with('role')
            ->whereIn('id', $rows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $estates = HousingEstate::query()->pluck('name', 'id');

        return $rows->map(function ($row) use ($users, $estates): ?array {
            $user = $users[$row->user_id];

            if (! $user) {
                return null;
            }

            return [
                'user' => $user,
                'role' => $user->role?->name ?? 'Tanpa Peran',
                'estate' => $user->housing_estate_id ? ($estates[$user->housing_estate_id] ?? '-') : 'Platform',
                'total' => (int) $row->total,
                'lastVisit' => $row->last_visit,
            ];
        })->filter()->values();
    }

    /**
     * Sebaran perangkat. Dihitung di PHP karena pengelompokannya bergantung pada
     * user agent, dan baris yang diambil sudah dibatasi 30 hari terakhir.
     *
     * @return array<int, array{label: string, total: int, share: float}>
     */
    protected function deviceShare(Carbon $since): array
    {
        $agents = PageView::query()
            ->since($since)
            ->orderByDesc('id')
            ->limit(1000)
            ->pluck('user_agent');

        if ($agents->isEmpty()) {
            return [];
        }

        $counts = $agents
            ->map(fn (?string $agent) => (new PageView(['user_agent' => $agent]))->deviceLabel())
            ->countBy()
            ->sortDesc();

        $total = $counts->sum();

        return $counts->map(fn (int $count, string $label) => [
            'label' => $label,
            'total' => $count,
            'share' => $total > 0 ? round(($count / $total) * 100) : 0,
        ])->values()->all();
    }

    /**
     * Hal yang perlu ditindaklanjuti super admin.
     *
     * @return array<string, array{label: string, total: int|float, hint: string}>
     */
    protected function needsAttention(): array
    {
        return [
            'payments' => [
                'label' => 'Pembayaran menunggu verifikasi',
                'total' => Payment::query()->pending()->count(),
                'hint' => 'Nilai tertahan: '.Currency::rupiah(Payment::query()->pending()->sum('amount')),
            ],
            'complaints' => [
                'label' => 'Pengaduan belum selesai',
                'total' => Complaint::query()->open()->count() + Complaint::query()->inProgress()->count(),
                'hint' => 'Masih menunggu tindak lanjut pengelola',
            ],
            'loans' => [
                'label' => 'Peminjaman barang aktif',
                'total' => ItemLoan::query()->outstanding()->count(),
                'hint' => 'Barang belum kembali ke pengelola',
            ],
            'silentUsers' => [
                'label' => 'Akun belum pernah masuk',
                'total' => User::query()->whereNull('last_login_at')->count(),
                'hint' => 'Akun terdaftar yang belum pernah login',
            ],
        ];
    }
}
