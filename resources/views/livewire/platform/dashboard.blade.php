@php
    // Panjang grafik diambil dari data, supaya batang kecil tetap terlihat.
    $maxTrend = max(array_map(fn ($day) => $day['total'], $trend) ?: [0]) ?: 1;
    $num = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A]">Dashboard Platform</h1>
            <p class="text-[14px] text-[#64748B]">
                Seberapa sering, oleh siapa, dan fitur apa yang paling dipakai dalam {{ $windowDays }} hari terakhir.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total Kunjungan', 'value' => $num($visits['total']), 'hint' => $num($visits['today']).' kunjungan hari ini', 'icon' => 'eye', 'chip' => 'bg-teal-50 text-teal-600'],
            ['label' => 'Pengunjung Unik', 'value' => $num($visits['uniqueVisitors']), 'hint' => $num($visits['guestVisits']).' kunjungan tanpa login', 'icon' => 'users-round', 'chip' => 'bg-sky-50 text-sky-600'],
            ['label' => 'Rata-rata per Hari', 'value' => number_format($visits['perDay'], 1, ',', '.'), 'hint' => 'Rata-rata '.$windowDays.' hari terakhir', 'icon' => 'activity', 'chip' => 'bg-indigo-50 text-indigo-600'],
            ['label' => 'Pengguna Aktif', 'value' => $num($platform['activeThisWeek']), 'hint' => 'Login 7 hari terakhir dari '.$num($platform['users']).' akun', 'icon' => 'user-check', 'chip' => 'bg-amber-50 text-amber-600'],
        ] as $card)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[13px] font-medium text-[#64748B]">{{ $card['label'] }}</p>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $card['chip'] }}">
                        <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                    </span>
                </div>
                <p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ $card['value'] }}</p>
                <p class="mt-1 text-[12px] text-[#94A3B8]">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <p class="font-bold text-[#0F172A]">Tren Kunjungan</p>
                <span class="rounded-full bg-teal-50 px-3 py-1 text-[12px] font-semibold text-teal-700">
                    {{ count($trend) }} hari terakhir
                </span>
            </div>

            @if (array_sum(array_column($trend, 'total')) === 0)
                <div class="mt-4">
                    <x-ui.empty-state icon="activity" title="Belum ada kunjungan"
                        subtitle="Angka muncul setelah ada halaman aplikasi yang dibuka." />
                </div>
            @else
                <div class="mt-5 flex h-40 items-end gap-1.5">
                    @foreach ($trend as $day)
                        <div class="group flex h-full flex-1 flex-col justify-end"
                            title="{{ $day['label'] }}: {{ $num($day['total']) }} kunjungan, {{ $num($day['visitors']) }} pengunjung">
                            <div class="w-full rounded-t-md bg-teal-600/80 transition group-hover:bg-teal-700"
                                style="height: {{ $day['total'] > 0 ? max(4, round($day['total'] / $maxTrend * 100)) : 1 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-1.5 text-center text-[10px] text-[#94A3B8]">
                    @foreach ($trend as $day)
                        <span class="flex-1">{{ $day['short'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Perangkat Pengunjung</p>
            @forelse ($devices as $device)
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[13px]">
                        <span class="text-[#475569]">{{ $device['label'] }}</span>
                        <span class="font-semibold text-[#0F172A]">{{ $device['share'] }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
                        <div class="h-full rounded-full bg-sky-500" style="width: {{ $device['share'] }}%"></div>
                    </div>
                    <p class="mt-1 text-[11px] text-[#94A3B8]">{{ $num($device['total']) }} kunjungan</p>
                </div>
            @empty
                <x-ui.empty-state icon="smartphone" title="Belum ada data perangkat"
                    subtitle="Data muncul setelah pengunjung membuka halaman aplikasi." />
            @endforelse
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