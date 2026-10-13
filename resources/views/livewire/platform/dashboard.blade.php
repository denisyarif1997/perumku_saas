@php
    $num = fn ($value) => number_format((float) $value, 0, ',', '.');
    $rupiah = fn (float|int $value) => \App\Support\Currency::rupiah($value);

    // Pertumbuhan jumlah perumahan aktif dibanding bulan lalu.
    $estateGrowth = $platform['newEstatesLastMonth'] > 0
        ? round(($platform['newEstatesThisMonth'] - $platform['newEstatesLastMonth']) / $platform['newEstatesLastMonth'] * 100, 1)
        : null;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A]">Dashboard Platform</h1>
            <p class="text-[14px] text-[#64748B]">
                Kesehatan bisnis &amp; pemakaian aplikasi di seluruh condominan dalam {{ $windowDays }} hari terakhir.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('platform.registrations') }}" wire:navigate
                class="flex min-h-[40px] items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-4 text-[14px] font-semibold text-[#334155] shadow-sm">
                <i data-lucide="user-check" class="h-4 w-4"></i> Verifikasi Perumahan
            </a>
            <a href="{{ route('admin.activity-logs.index') }}" wire:navigate
                class="flex min-h-[40px] items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-4 text-[14px] font-semibold text-[#334155] shadow-sm">
                <i data-lucide="scroll-text" class="h-4 w-4"></i> Log aktivitas
            </a>
            <a href="{{ route('admin.estates.index') }}" wire:navigate
                class="flex min-h-[40px] items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-4 text-[14px] font-semibold text-[#334155] shadow-sm">
                <i data-lucide="building-2" class="h-4 w-4"></i> Kelola condominan
            </a>
        </div>
    </div>

    {{-- KPI utama: perumahan aktif, pengguna (DAU/MAU), MRR, kunjungan, status khusus. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-[13px] font-medium text-[#64748B]">Total Perumahan Aktif</p>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ $num($platform['estatesActive']) }}</p>
            <p class="mt-1 text-[12px] text-[#94A3B8]">
                dari {{ $num($platform['estates']) }} terdaftar &middot;
                @if ($estateGrowth === null)
                    {{ $num($platform['newEstatesThisMonth']) }} baru bulan ini
                @else
                    <span class="{{ $estateGrowth >= 0 ? 'text-teal-600' : 'text-rose-600' }}">
                        {{ $estateGrowth >= 0 ? '+' : '' }}{{ $estateGrowth }}%
                    </span>
                    vs bulan lalu
                @endif
            </p>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-[13px] font-medium text-[#64748B]">Pengguna Aktif (DAU/MAU)</p>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <i data-lucide="users-round" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ $num($visits['dau']) }} <span class="text-[13px] font-semibold text-[#94A3B8]">/ {{ $num($visits['uniqueVisitors']) }}</span></p>
            <p class="mt-1 text-[12px] text-[#94A3B8]">DAU hari ini &middot; MAU {{ $windowDays }} hari dari {{ $num($platform['users']) }} akun</p>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-[13px] font-medium text-[#64748B]">Pendapatan Subscription (MRR)</p>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <i data-lucide="banknote" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ $rupiah($finance['mrr']) }}</p>
            <p class="mt-1 text-[12px] text-[#94A3B8]">ARR {{ $rupiah($finance['arr']) }} &middot; {{ $rupiah($finance['revenue30']) }} masuk {{ $windowDays }} hari</p>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-[13px] font-medium text-[#64748B]">Total Kunjungan</p>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i data-lucide="eye" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ $num($visits['total']) }}</p>
            <p class="mt-1 text-[12px] text-[#94A3B8]">{{ $num($visits['today']) }} hari ini &middot; {{ $num($visits['allTime']) }} sepanjang waktu</p>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-[13px] font-medium text-[#64748B]">Status Pengguna Khusus</p>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i data-lucide="badge-alert" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 space-y-1.5">
                <div class="flex items-center justify-between gap-2 text-[14px]">
                    <span class="text-[#475569]">Trial</span>
                    <x-ui.badge :color="$finance['trialEndingSoon'] > 0 ? 'amber' : 'sky'">
                        {{ $num($finance['trialCount']) }} perumahan
                    </x-ui.badge>
                </div>
                <div class="flex items-center justify-between gap-2 text-[14px]">
                    <span class="text-[#475569]">Belum bayar</span>
                    <x-ui.badge :color="$finance['overdueCount'] > 0 ? 'red' : 'slate'">
                        {{ $num($finance['unpaidCount']) }} tagihan
                    </x-ui.badge>
                </div>
            </div>
            <p class="mt-2 text-[12px] text-[#94A3B8]">
                {{ $num($finance['trialEndingSoon']) }} trial berakhir ≤ 3 hari &middot; tunggakan {{ $rupiah($finance['overdueAmount']) }}
            </p>
        </div>
    </div>

    @if ($finance['trialEndingSoon'] > 0 || $finance['overdueCount'] > 0)
        <x-ui.alert type="warning" icon="bell-ring">
            <b>{{ $num($finance['trialEndingSoon']) }} perumahan</b> akan mengakhiri masa trial dalam ≤ 3 hari dan
            <b>{{ $num($finance['overdueCount']) }} tagihan</b> ({{ $rupiah($finance['overdueAmount']) }}) sudah lewat jatuh tempo.
            Kirim pengingat lewat tabel tunggakan di bawah sebelum layanan terkunci otomatis.
        </x-ui.alert>
    @endif

    @php
        // Geometri line chart SVG murni (tanpa library chart).
        $chartW = 600;
        $chartH = 150;
        $chartPad = 8;
        $revenueMax = max(array_column($revenueTrend, 'revenue')) ?: 1;
        $usersMax = max(array_column($revenueTrend, 'users')) ?: 1;
        $chartStep = count($revenueTrend) > 1 ? ($chartW - 2 * $chartPad) / (count($revenueTrend) - 1) : 0;
        $pointX = fn (int $i) => $chartPad + $i * $chartStep;
        $pointY = fn (float $value, float $max) => $chartH - $chartPad - ($value / $max) * ($chartH - 2 * $chartPad);
        $revenuePoints = [];
        $usersPoints = [];

        foreach ($revenueTrend as $i => $month) {
            $revenuePoints[] = round($pointX($i), 1).','.round($pointY($month['revenue'], $revenueMax), 1);
            $usersPoints[] = round($pointX($i), 1).','.round($pointY($month['users'], $usersMax), 1);
        }
    @endphp

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-bold text-[#0F172A]">Tren Pendapatan &amp; Pengguna (12 Bulan)</p>
                <div class="flex items-center gap-3 text-[12px] font-semibold">
                    <span class="flex items-center gap-1.5 text-teal-700">
                        <span class="h-2 w-2 rounded-full bg-teal-600"></span> Pendapatan
                    </span>
                    <span class="flex items-center gap-1.5 text-indigo-700">
                        <span class="h-2 w-2 rounded-full bg-indigo-500"></span> Pengguna baru
                    </span>
                </div>
            </div>

            @if (array_sum(array_column($revenueTrend, 'revenue')) === 0 && array_sum(array_column($revenueTrend, 'users')) === 0)
                <div class="mt-4">
                    <x-ui.empty-state icon="trending-up" title="Belum ada data tren"
                        subtitle="Grafik terisi setelah ada pembayaran langganan atau pengguna baru terdaftar." />
                </div>
            @else
                <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" class="mt-4 h-44 w-full" role="img"
                    aria-label="Grafik tren pendapatan dan pengguna baru 12 bulan terakhir">
                    @for ($line = 1; $line <= 3; $line++)
                        <line x1="0" x2="{{ $chartW }}" y1="{{ round($chartH / 4 * $line, 1) }}"
                            y2="{{ round($chartH / 4 * $line, 1) }}" stroke="#EEF2F1" stroke-width="1" />
                    @endfor
                    <polyline points="{{ implode(' ', $revenuePoints) }}" fill="none" stroke="#0D9488"
                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline points="{{ implode(' ', $usersPoints) }}" fill="none" stroke="#6366F1"
                        stroke-width="2.5" stroke-dasharray="6 4" stroke-linecap="round" stroke-linejoin="round" />
                    @foreach ($revenueTrend as $i => $month)
                        <circle cx="{{ round($pointX($i), 1) }}" cy="{{ round($pointY($month['revenue'], $revenueMax), 1) }}"
                            r="3" fill="#0D9488">
                            <title>{{ $month['label'] }}: {{ $rupiah($month['revenue']) }} pendapatan</title>
                        </circle>
                        <circle cx="{{ round($pointX($i), 1) }}" cy="{{ round($pointY($month['users'], $usersMax), 1) }}"
                            r="3" fill="#6366F1">
                            <title>{{ $month['label'] }}: {{ $num($month['users']) }} pengguna baru</title>
                        </circle>
                    @endforeach
                </svg>
                <div class="mt-1 flex text-[10px] text-[#94A3B8]">
                    @foreach ($revenueTrend as $month)
                        <span class="flex-1 text-center">{{ $month['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Distribusi Status Langganan</p>

            @if ($statusBreakdown === [])
                <x-ui.empty-state icon="chart-pie" title="Belum ada langganan"
                    subtitle="Distribusi muncul setelah perumahan memiliki status langganan." />
            @else
                <div class="mt-4 flex items-center gap-5">
                    @php
                        $pieStops = [];
                        $pieDegrees = 0;

                        foreach ($statusBreakdown as $segment) {
                            $slice = $segment['percent'] * 3.6;
                            $pieStops[] = $segment['color'].' '.$pieDegrees.'deg '.($pieDegrees + $slice).'deg';
                            $pieDegrees += $slice;
                        }
                    @endphp

                    <div class="relative h-28 w-28 shrink-0">
                        <div class="h-full w-full rounded-full"
                            style="background: conic-gradient({{ implode(', ', $pieStops) }})"></div>
                        <div class="absolute inset-4 flex items-center justify-center rounded-full bg-white">
                            <span class="text-[15px] font-bold text-[#0F172A]">
                                {{ $num(array_sum(array_column($statusBreakdown, 'count'))) }}
                            </span>
                        </div>
                    </div>
                    <ul class="min-w-0 flex-1 space-y-2 text-[13px]">
                        @foreach ($statusBreakdown as $segment)
                            <li class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2 text-[#475569]">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $segment['color'] }}"></span>
                                    <span class="truncate">{{ $segment['label'] }}</span>
                                </span>
                                <span class="shrink-0 font-semibold text-[#0F172A]">
                                    {{ $num($segment['count']) }} ({{ $segment['percent'] }}%)
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-4 space-y-2 border-t border-[#EEF2F1] pt-4 text-[13px]">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#475569]">Konversi Trial → Berbayar</span>
                        <span class="font-semibold {{ $finance['conversionRate'] >= 50 ? 'text-teal-600' : 'text-amber-600' }}">
                            {{ $finance['conversionRate'] }}%
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#475569]">Churn Rate (30 hari)</span>
                        <span class="font-semibold {{ $finance['churnRate'] > 10 ? 'text-rose-600' : 'text-[#0F172A]' }}">
                            {{ $finance['churnRate'] }}%
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Menu populer + daftar tunggakan subscription dengan tautan pengingat. --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Menu Paling Sering Diakses</p>
            <p class="text-[13px] text-[#64748B]">Dikelompokkan dari halaman yang dibuka {{ $windowDays }} hari terakhir.</p>

            @php
                $menuMax = max(array_column($topMenus->all(), 'total') ?: [0]) ?: 1;
            @endphp

            @forelse ($topMenus as $menu)
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[13px]">
                        <span class="text-[#475569]">{{ $menu['label'] }}</span>
                        <span class="font-semibold text-[#0F172A]">{{ $num($menu['total']) }}</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
                        <div class="h-full rounded-full bg-teal-600" style="width: {{ max(4, round($menu['total'] / $menuMax * 100)) }}%"></div>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="layout-grid" title="Belum ada kunjungan"
                    subtitle="Angka muncul setelah pengelola atau warga membuka halaman aplikasi." />
            @endforelse
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#EEF2F1] px-5 py-4">
                <div>
                    <p class="font-bold text-[#0F172A]">Tunggakan Subscription</p>
                    <p class="text-[13px] text-[#64748B]">Tagihan belum lunas; kirim pengingat langsung ke pengurus perumahan.</p>
                </div>
                <div class="flex items-center gap-2 text-[12px] font-semibold">
                    <span class="rounded-full bg-teal-50 px-3 py-1 text-teal-700">{{ $num($finance['paidInvoices']) }} lunas</span>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700">{{ $num($finance['pendingCount']) }} pending</span>
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">{{ $num($finance['overdueCount']) }} jatuh tempo</span>
                </div>
            </div>

            @if ($arrears->isEmpty())
                <x-ui.empty-state icon="circle-check-big" title="Tidak ada tunggakan"
                    subtitle="Seluruh tagihan langganan sudah lunas." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-[14px]">
                        <thead class="bg-[#F8FAFC] text-left text-[12px] uppercase tracking-wide text-[#64748B]">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Perumahan</th>
                                <th class="px-3 py-3 font-semibold">Tagihan</th>
                                <th class="px-3 py-3 text-right font-semibold">Jumlah</th>
                                <th class="px-3 py-3 font-semibold">Jatuh Tempo</th>
                                <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F1]">
                            @foreach ($arrears as $row)
                                <tr class="hover:bg-[#F8FAFC]">
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-[#0F172A]">{{ $row['estate']?->name ?? '-' }}</p>
                                        <p class="text-[12px] text-[#94A3B8]">{{ $row['estate']?->code ?? '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-mono text-[13px] text-[#334155]">{{ $row['invoice']->number }}</p>
                                        <p class="text-[12px] text-[#94A3B8]">{{ $row['invoice']->period_label ?? 'Tanpa periode' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold text-[#0F172A]">{{ $rupiah($row['invoice']->total_amount) }}</td>
                                    <td class="px-3 py-3">
                                        <x-ui.badge :color="$row['daysOverdue'] !== null ? 'red' : 'amber'">
                                            {{ $row['daysOverdue'] !== null ? 'Telat '.$row['daysOverdue'].' hari' : ($row['invoice']->due_at?->format('d M Y') ?? 'Tanpa tenggat') }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($row['waLink'])
                                                <a href="{{ $row['waLink'] }}" target="_blank" rel="noopener noreferrer"
                                                    class="flex min-h-[34px] items-center gap-1.5 rounded-lg bg-teal-600 px-3 text-[12px] font-semibold text-white hover:bg-teal-700">
                                                    <i data-lucide="message-circle" class="h-3.5 w-3.5"></i> WhatsApp
                                                </a>
                                            @endif
                                            @if ($row['emailLink'])
                                                <a href="{{ $row['emailLink'] }}"
                                                    class="flex min-h-[34px] items-center gap-1.5 rounded-lg border border-[#E2E8F0] px-3 text-[12px] font-semibold text-[#334155] hover:bg-[#F8FAFC]">
                                                    <i data-lucide="mail" class="h-3.5 w-3.5"></i> Email
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Pemakaian Fitur</p>
            <p class="text-[13px] text-[#64748B]">Jumlah data baru per fitur dalam {{ $windowDays }} hari terakhir.</p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($features as $feature)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-[#F8FAFC] px-3 py-2.5">
                        <span class="flex min-w-0 items-center gap-2 text-[13px] text-[#475569]">
                            <i data-lucide="{{ $feature['icon'] }}" class="h-4 w-4 shrink-0 text-teal-600"></i>
                            <span class="truncate">{{ $feature['label'] }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block text-[14px] font-bold text-[#0F172A]">{{ $num($feature['period']) }}</span>
                            <span class="block text-[10px] text-[#94A3B8]">dari {{ $num($feature['total']) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 border-t border-[#EEF2F1] pt-4">
                <p class="text-[13px] font-bold text-[#0F172A]">Fitur Sepi Peminat</p>
                <p class="text-[12px] text-[#64748B]">Kandidat evaluasi fitur: pemakaian terendah dalam {{ $windowDays }} hari.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($quietFeatures as $feature)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-[12px] font-semibold text-amber-700">
                            <i data-lucide="{{ $feature['icon'] }}" class="h-3.5 w-3.5"></i>
                            {{ $feature['label'] }} &middot; {{ $num($feature['period']) }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Modul Paling Aktif</p>
            <p class="text-[13px] text-[#64748B]">Berdasarkan aktivitas yang dicatat di log aplikasi.</p>

            @forelse ($modules as $module)
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[13px]">
                        <span class="text-[#475569]">{{ $module['label'] }}</span>
                        <span class="font-semibold text-[#0F172A]">{{ $num($module['total']) }}</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
                        <div class="h-full rounded-full bg-teal-600" style="width: {{ $module['share'] }}%"></div>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="scroll-text" title="Belum ada aktivitas"
                    subtitle="Log aktivitas terisi saat pengelola atau warga mengubah data." />
            @endforelse
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Halaman Paling Dikunjungi</p>
            <ol class="mt-4 space-y-3 text-[14px]">
                @forelse ($topPages as $page)
                    <li class="flex items-center justify-between gap-3">
                        <span class="truncate font-mono text-[13px] text-[#475569]">{{ $page['path'] }}</span>
                        <span class="shrink-0 font-semibold text-[#0F172A]">{{ $num($page['total']) }}</span>
                    </li>
                @empty
                    <li class="text-[#94A3B8]">Belum ada data.</li>
                @endforelse
            </ol>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Pengguna Paling Aktif</p>
            <ol class="mt-4 space-y-3 text-[14px]">
                @forelse ($topUsers as $row)
                    <li class="flex items-center justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-[#0F172A]">{{ $row['user']->name }}</span>
                            <span class="block truncate text-[11px] text-[#94A3B8]">{{ $row['role'] }} &middot; {{ $row['estate'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold text-[#0F172A]">{{ $num($row['total']) }}</span>
                    </li>
                @empty
                    <li class="text-[#94A3B8]">Belum ada data.</li>
                @endforelse
            </ol>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Perlu Perhatian</p>
            <ul class="mt-4 space-y-3 text-[14px]">
                @foreach ($attention as $item)
                    <li class="flex items-start justify-between gap-3">
                        <span class="min-w-0 text-[#475569]">
                            <span class="block">{{ $item['label'] }}</span>
                            <span class="block text-[11px] text-[#94A3B8]">{{ $item['hint'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold {{ $item['total'] > 0 ? 'text-amber-600' : 'text-[#94A3B8]' }}">{{ $num($item['total']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
        <div class="border-b border-[#EEF2F1] px-5 py-4">
            <p class="font-bold text-[#0F172A]">Pemakaian per Kondominan</p>
            <p class="text-[13px] text-[#64748B]">Kunjungan, pengunjung, dan jumlah akun tiap klien.</p>
        </div>

        @if ($estateRows->isEmpty())
            <x-ui.empty-state icon="building-2" title="Belum ada condominan"
                subtitle="Daftarkan condominan pertama untuk mulai melihat pemakaian aplikasi." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-[14px]">
                    <thead class="bg-[#F8FAFC] text-left text-[12px] uppercase tracking-wide text-[#64748B]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Kondominan</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold">Kunjungan</th>
                            <th class="px-3 py-3 text-right font-semibold">Pengunjung</th>
                            <th class="px-3 py-3 text-right font-semibold">Akun</th>
                            <th class="px-3 py-3 text-right font-semibold">Warga</th>
                            <th class="px-5 py-3 text-right font-semibold">Kunjungan Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEF2F1]">
                        @foreach ($estateRows as $row)
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.estates.index') }}" wire:navigate class="font-semibold text-[#0F172A] hover:text-teal-700">
                                        {{ $row['estate']->name }}
                                    </a>
                                    <p class="text-[12px] text-[#94A3B8]">{{ $row['estate']->code }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <x-ui.badge :color="$row['estate']->status === 'active' ? 'green' : 'slate'">
                                        {{ $row['estate']->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $num($row['visits']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $num($row['visitors']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $num($row['users']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $num($row['residents']) }}</td>
                                <td class="px-5 py-3 text-right text-[13px] text-[#64748B]">
                                    {{ $row['lastVisit'] ? \Illuminate\Support\Carbon::parse($row['lastVisit'])->diffForHumans() : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm lg:col-span-2">
            <p class="font-bold text-[#0F172A]">Aktivitas Terbaru</p>
            @forelse ($recentActivity as $log)
                <div class="mt-4 flex items-start justify-between gap-3 border-b border-[#F1F5F9] pb-3 last:border-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="truncate text-[14px] text-[#334155]">{{ $log->description ?? 'Aktivitas aplikasi' }}</p>
                        <p class="text-[12px] text-[#94A3B8]">
                            {{ $log->user?->name ?? 'System' }} &middot; {{ \App\Models\ActivityLog::moduleLabel($log->module) }}
                        </p>
                    </div>
                    <span class="shrink-0 text-[12px] text-[#94A3B8]">{{ $log->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="scroll-text" title="Belum ada aktivitas"
                    subtitle="Log terisi saat pengelola atau warga mengubah data." />
            @endforelse
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Ringkasan Platform</p>
            <dl class="mt-4 space-y-3 text-[14px]">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Kondominan</dt>
                    <dd class="font-semibold text-[#0F172A]">{{ $num($platform['estatesActive']) }} / {{ $num($platform['estates']) }} aktif</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Kondominan baru</dt>
                    <dd class="font-semibold text-[#0F172A]">{{ $num($platform['newEstatesThisMonth']) }} bulan ini</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Akun terdaftar</dt>
                    <dd class="font-semibold text-[#0F172A]">{{ $num($platform['users']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Status pengguna</dt>
                    <dd class="font-semibold text-[#0F172A]">
                        {{ $num($userStatus['active']) }} aktif &middot;
                        {{ $num($userStatus['trial']) }} trial &middot;
                        {{ $num($userStatus['inactive']) }} non-aktif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Akun baru</dt>
                    <dd class="font-semibold text-[#0F172A]">{{ $num($platform['newUsersThisMonth']) }} bulan ini</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Belum pernah masuk</dt>
                    <dd class="font-semibold text-[#0F172A]">{{ $num($platform['neverLoggedIn']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-[#475569]">Warga</dt>
                    <dd class="font-semibold text-[#0F172A]">{{ $num($platform['residents']) }}</dd>
                </div>
            </dl>

            <div class="mt-5 border-t border-[#EEF2F1] pt-4">
                <div class="flex items-center justify-between text-[13px]">
                    <span class="text-[#64748B]">Tunggakan iuran</span>
                    <span class="font-semibold text-rose-600">@rupiah($platform['outstanding'])</span>
                </div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
                    <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-teal-700"
                        style="width: {{ min(100, max(0, $platform['collectionRate'])) }}%"></div>
                </div>
                <p class="mt-1 text-[11px] text-[#94A3B8]">{{ $platform['collectionRate'] }}% dari @rupiah($platform['billed']) terkumpul</p>
            </div>
        </div>
    </div>
</div>