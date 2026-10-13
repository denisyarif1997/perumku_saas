<?php

namespace App\Livewire\Platform;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Billing;
use App\Models\ChessGame;
use App\Models\Complaint;
use App\Models\EstateSubscription;
use App\Models\EstateSubscriptionInvoice;
use App\Models\HousingEstate;
use App\Models\ItemLoan;
use App\Models\PageView;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Resident;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Support\AdminMenu;
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
 * Fokusnya adalah kesehatan bisnis SaaS platform: pendapatan langganan
 * (MRR/ARR), status pembayaran, konversi trial→berbayar, churn, serta
 * pertumbuhan perumahan dan pengguna. Di bawahnya tersaji tren pendapatan
 * 12 bulan, distribusi status langganan, menu yang paling sering diakses,
 * dan daftar tunggakan subscription dengan tautan pengingat WA/email.
 *
 * Semua agregat diambil lewat query groupBy (bukan N+1), lalu dirangkai di
 * memori. Jumlah condominan masih sedikit, jadi ini jauh lebih mudah dibaca
 * daripada subquery bertingkat.
 */
class Dashboard extends Component
{
    /** Panjang grafik tren pendapatan & pengguna (bulan). */
    protected const TREND_MONTHS = 12;

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
            'finance' => $this->subscriptionFinance(),
            'revenueTrend' => $this->revenueTrend(),
            'statusBreakdown' => $this->subscriptionStatusBreakdown(),
            'arrears' => $this->arrears(),
            'platform' => $this->platformSummary(),
            'userStatus' => $this->userStatusBreakdown(),
            'features' => $this->featureUsage($since),
            'quietFeatures' => $this->quietFeatures($since),
            'modules' => $this->moduleUsage($since),
            'estateRows' => $this->estateUsage($since),
            'topPages' => $this->topPages($since),
            'topMenus' => $this->topMenus($since),
            'topUsers' => $this->topUsers($since),
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
            'dau' => PageView::query()
                ->whereNotNull('user_id')
                ->whereDate('created_at', now()->toDateString())
                ->distinct()
                ->count('user_id'),
            'uniqueVisitors' => (clone $recent)->whereNotNull('user_id')->distinct()->count('user_id'),
            'guestVisits' => (clone $recent)->whereNull('user_id')->count(),
            'perDay' => $total / self::WINDOW_DAYS,
            'allTime' => PageView::query()->count(),
        ];
    }

    /**
     * Metrik finansial langganan platform: MRR/ARR, pendapatan30 hari,
     * status pembayaran invoice, konversi trial→berbayar, dan churn.
     *
     * @return array<string, float|int>
     */
    protected function subscriptionFinance(): array
    {
        $since = now()->subDays(self::WINDOW_DAYS - 1)->startOfDay();

        // MRR = total invoice paket bulanan (base) yang lunas dan periodenya
        // masih berjalan hari ini — pendapatan berulang yang benar-benar aktif.
        $mrr = (float) EstateSubscriptionInvoice::query()
            ->where('type', 'base')
            ->where('status', 'paid')
            ->whereDate('period_end', '>=', now()->toDateString())
            ->sum('total_amount');

        $revenue30 = (float) EstateSubscriptionInvoice::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->sum('total_amount');

        $unpaid = EstateSubscriptionInvoice::query()
            ->whereIn('status', ['pending', 'failed', 'expired']);

        $overdueCount = (clone $unpaid)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', now()->toDateString())
            ->count();

        $pendingCount = (clone $unpaid)->count() - $overdueCount;

        // Konversi: dari seluruh langganan (semuanya pernah trial), berapa yang
        // minimal sekali membayar paket bulanan.
        $totalSubs = EstateSubscription::query()->count();
        $paidEstates = EstateSubscriptionInvoice::query()
            ->where('type', 'base')
            ->where('status', 'paid')
            ->distinct()
            ->count('housing_estate_id');

        // Churn: langganan yang berakhir (ditangguhkan/dibatalkan) dalam30 hari.
        $churned = EstateSubscription::query()
            ->where(function ($query) use ($since) {
                $query->where('cancelled_at', '>=', $since)
                    ->orWhere('suspended_at', '>=', $since);
            })
            ->count();

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'revenue30' => $revenue30,
            'paidInvoices' => EstateSubscriptionInvoice::query()->where('status', 'paid')->count(),
            'pendingCount' => $pendingCount,
            'overdueCount' => $overdueCount,
            'overdueAmount' => (float) (clone $unpaid)
                ->whereNotNull('due_at')
                ->whereDate('due_at', '<', now()->toDateString())
                ->sum('total_amount'),
            'trialCount' => EstateSubscription::query()->where('status', 'trial')->count(),
            'trialEndingSoon' => EstateSubscription::query()
                ->where('status', 'trial')
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<=', now()->addDays(3))
                ->count(),
            'unpaidCount' => $pendingCount + $overdueCount,
            'conversionRate' => $totalSubs > 0 ? round($paidEstates / $totalSubs * 100, 1) : 0.0,
            'churnRate' => $totalSubs > 0 ? round($churned / $totalSubs * 100, 1) : 0.0,
            'monthlyPrice' => app(SubscriptionService::class)->monthlyPrice(),
        ];
    }

    /**
     * Tren12 bulan terakhir: pendapatan langganan lunas dan pengguna baru
     * per bulan (dirangkai dari agregat harian agar tetap portabel).
     *
     * @return array<int, array{label: string, revenue: int, users: int}>
     */
    protected function revenueTrend(): array
    {
        $start = now()->subMonths(self::TREND_MONTHS - 1)->startOfMonth();

        $revenue = EstateSubscriptionInvoice::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $users = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $revenueByMonth = [];
        $usersByMonth = [];

        foreach ($revenue as $day => $total) {
            $key = substr((string) $day, 0, 7);
            $revenueByMonth[$key] = ($revenueByMonth[$key] ?? 0) + (int) $total;
        }

        foreach ($users as $day => $total) {
            $key = substr((string) $day, 0, 7);
            $usersByMonth[$key] = ($usersByMonth[$key] ?? 0) + (int) $total;
        }

        $series = [];

        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $key = $month->format('Y-m');

            $series[] = [
                'label' => $month->format('M y'),
                'revenue' => $revenueByMonth[$key] ?? 0,
                'users' => $usersByMonth[$key] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Distribusi status langganan untuk pie chart: aktif, trial, menunggak,
     * dan berhenti (churned). Persentase dihitung terhadap seluruh langganan.
     *
     * @return array<int, array{label: string, count: int, percent: float, color: string}>
     */
    protected function subscriptionStatusBreakdown(): array
    {
        $rows = EstateSubscription::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $rows->sum();

        if ($total === 0) {
            return [];
        }

        $buckets = [
            ['label' => 'Aktif', 'count' => (int) ($rows['active'] ?? 0), 'color' => '#10B981'],
            ['label' => 'Trial', 'count' => (int) ($rows['trial'] ?? 0), 'color' => '#0EA5E9'],
            ['label' => 'Menunggak', 'count' => (int) ($rows['past_due'] ?? 0), 'color' => '#F59E0B'],
            ['label' => 'Berhenti', 'count' => (int) ($rows['suspended'] ?? 0) + (int) ($rows['cancelled'] ?? 0), 'color' => '#F43F5E'],
        ];

        return array_map(fn (array $bucket) => $bucket + [
            'percent' => round($bucket['count'] / $total * 100, 1),
        ], $buckets);
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
            'newEstatesLastMonth' => HousingEstate::query()
                ->whereYear('created_at', now()->subMonth()->year)
                ->whereMonth('created_at', now()->subMonth()->month)
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
     * Status pengguna platform: aktif, sedang menikmati masa trial perumahan,
     * dan non-aktif/suspended.
     *
     * @return array<string, int>
     */
    protected function userStatusBreakdown(): array
    {
        $trialEstateIds = EstateSubscription::query()
            ->where('status', 'trial')
            ->pluck('housing_estate_id');

        return [
            'active' => User::query()->where('status', 'active')->count(),
            'trial' => User::query()
                ->whereIn('housing_estate_id', $trialEstateIds)
                ->count(),
            'inactive' => User::query()->where('status', '!=', 'active')->count(),
        ];
    }

    /**
     * Fitur yang paling sepi dalam jendela 30 hari (pemakaian terendah),
     * dipakai untuk bahan evaluasi produk.
     *
     * @return array<int, array{label: string, icon: string, period: int, total: int}>
     */
    protected function quietFeatures(Carbon $since): array
    {
        return collect($this->featureUsage($since))
            ->sortBy([['period', 'asc'], ['total', 'asc']])
            ->take(3)
            ->values()
            ->all();
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
     * Halaman paling sering dibuka dipetakan menjadi label menu yang manusiawi
     * (mis. /admin/forum → Forum Warga), lalu diagregasi per menu.
     *
     * @return Collection<int, array{label: string, path: string, total: int}>
     */
    protected function topMenus(Carbon $since)
    {
        $patterns = [];

        foreach (AdminMenu::sections() as $section) {
            foreach ($section['items'] as $item) {
                // 'admin.forum.*' → '/admin/forum'; 'admin.dashboard' → '/admin/dashboard'.
                $prefix = '/'.str_replace('.', '/', rtrim($item[3], '.*'));

                $patterns[$prefix] = $item[2];
            }
        }

        // Prefiks terpanjang menang agar /admin/ipl/payments tidak tertimpa
        // pola yang lebih umum seperti /admin.
        uksort($patterns, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $this->topPages($since)
            ->map(function (array $page) use ($patterns): array {
                $label = null;

                foreach ($patterns as $prefix => $menuLabel) {
                    if (str_starts_with($page['path'], $prefix)) {
                        $label = $menuLabel;

                        break;
                    }
                }

                return [
                    'label' => $label ?? $page['path'],
                    'path' => $page['path'],
                    'total' => $page['total'],
                ];
            })
            ->groupBy('label')
            ->map(fn (Collection $rows, string $label) => [
                'label' => $label,
                'path' => $rows->first()['path'],
                'total' => (int) $rows->sum('total'),
            ])
            ->sortByDesc('total')
            ->take(6)
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
     * Daftar tagihan langganan yang belum lunas, diurutkan dari yang paling
     * lewat jatuh tempo. Setiap baris membawa tautan pengingat WhatsApp/email
     * ke kontak pengurus perumahan agar super admin bisa menagih cepat.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function arrears()
    {
        return EstateSubscriptionInvoice::query()
            ->with('estate')
            ->whereIn('status', ['pending', 'failed', 'expired'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->take(8)
            ->get()
            ->map(function (EstateSubscriptionInvoice $invoice): array {
                $estate = $invoice->estate;
                $phone = $this->contactPhone($estate);
                $email = $estate?->email;

                $message = "Halo, tagihan langganan Perumku {$invoice->number} "
                    .($invoice->period_label ? "periode {$invoice->period_label} " : '')
                    .'sebesar '.Currency::rupiah($invoice->total_amount)
                    .($invoice->due_at ? " jatuh tempo {$invoice->due_at->format('d M Y')}" : '')
                    .' belum kami terima. Mohon lakukan pembayaran agar layanan tetap aktif. Terima kasih.';

                $waLink = null;

                if ($phone !== null) {
                    $digits = preg_replace('/\D/', '', $phone);

                    if (str_starts_with($digits, '0')) {
                        $digits = '62'.substr($digits, 1);
                    }

                    $waLink = 'https://wa.me/'.$digits.'?text='.urlencode($message);
                }

                return [
                    'invoice' => $invoice,
                    'estate' => $estate,
                    'daysOverdue' => $invoice->due_at && $invoice->due_at->isPast()
                        ? abs((int) now()->startOfDay()->diffInDays($invoice->due_at->copy()->startOfDay()))
                        : null,
                    'waLink' => $waLink,
                    'emailLink' => $email !== null
                        ? 'mailto:'.$email.'?subject='.urlencode('Pengingat Tagihan Langganan '.$invoice->number).'&body='.urlencode($message)
                        : null,
                ];
            });
    }

    /**
     * Nomor telepon pengurus perumahan untuk tautan pengingat: kontak
     * perumahan lebih dulu, lalu jatuh ke akun admin perumahan tersebut.
     */
    protected function contactPhone(?HousingEstate $estate): ?string
    {
        if (! $estate) {
            return null;
        }

        if ($estate->phone) {
            return $estate->phone;
        }

        return User::query()
            ->where('housing_estate_id', $estate->id)
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->whereNotNull('phone')
            ->value('phone');
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
