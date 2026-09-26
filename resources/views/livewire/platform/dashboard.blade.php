<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A]">Dashboard Super Admin</h1>
            <p class="text-[14px] text-[#64748B]">Ringkasan seluruh condominan yang terdaftar di platform.</p>
        </div>
        <a href="{{ route('admin.estates.index') }}" wire:navigate
            class="flex min-h-[40px] items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-4 text-[14px] font-semibold text-[#334155] shadow-sm">
            <i data-lucide="building-2" class="h-4 w-4"></i> Kelola condominan
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total Klien', 'value' => number_format($totals['estates'], 0, ',', '.'), 'hint' => $totals['estatesActive'].' aktif, +'.$newEstatesThisMonth.' bulan ini', 'icon' => 'building-2', 'chip' => 'bg-teal-50 text-teal-600'],
            ['label' => 'Total User', 'value' => number_format($totals['users'], 0, ',', '.'), 'hint' => $activeUsers.' aktif, '.$inactiveUsers.' nonaktif', 'icon' => 'user-cog', 'chip' => 'bg-sky-50 text-sky-600'],
            ['label' => 'Total Warga', 'value' => number_format($totals['residents'], 0, ',', '.'), 'hint' => number_format($totals['houses'], 0, ',', '.').' rumah, '.$totals['blocks'].' blok', 'icon' => 'users', 'chip' => 'bg-indigo-50 text-indigo-600'],
            ['label' => 'Tagihan Terbit', 'value' => number_format($totals['billings'], 0, ',', '.'), 'hint' => \App\Support\Currency::rupiah($totals['billed']), 'icon' => 'file-text', 'chip' => 'bg-amber-50 text-amber-600'],
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
                <p class="font-bold text-[#0F172A]">Pendapatan Platform</p>
                <span class="rounded-full bg-teal-50 px-3 py-1 text-[12px] font-semibold text-teal-700">
                    {{ $totals['collectionRate'] }}% terkumpul
                </span>
            </div>
            <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-[#F1F5F9]">
                <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-teal-700"
                    style="width: {{ min(100, max(0, $totals['collectionRate'])) }}%"></div>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-[12px] text-[#64748B]">Total Ditagihkan</dt>
                    <dd class="text-lg font-bold text-[#0F172A]">@rupiah($totals['billed'])</dd>
                </div>
                <div>
                    <dt class="text-[12px] text-[#64748B]">Sudah Terbayar</dt>
                    <dd class="text-lg font-bold text-emerald-600">@rupiah($totals['paid'])</dd>
                </div>
                <div>
                    <dt class="text-[12px] text-[#64748B]">Tunggakan</dt>
                    <dd class="text-lg font-bold text-rose-600">@rupiah($totals['outstanding'])</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Perlu Perhatian</p>
            <ul class="mt-4 space-y-3 text-[14px]">
                <li class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-[#475569]">
                        <i data-lucide="clock" class="h-4 w-4 text-amber-500"></i> Pembayaran menunggu
                    </span>
                    <span class="font-semibold text-[#0F172A]">{{ $pendingPayments }}</span>
                </li>
                <li class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-[#475569]">
                        <i data-lucide="wallet" class="h-4 w-4 text-amber-500"></i> Nominal menunggu
                    </span>
                    <span class="font-semibold text-[#0F172A]">@rupiah($pendingPaymentsAmount)</span>
                </li>
                <li class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-[#475569]">
                        <i data-lucide="message-square-warning" class="h-4 w-4 text-amber-500"></i> Pengaduan terbuka
                    </span>
                    <span class="font-semibold text-[#0F172A]">{{ $openComplaints }}</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
        <p class="font-bold text-[#0F172A]">Tren Tagihan 6 Bulan Terakhir</p>
        @php $maxBilled = max(array_map(fn ($m) => $m['billed'], $trend) ?: [0]) ?: 1; @endphp
        <div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($trend as $month)
                <div class="rounded-xl border border-[#E2E8F0] p-3">
                    <p class="text-[12px] font-medium text-[#64748B]">{{ $month['label'] }}</p>
                    <div class="mt-2 h-16 w-full overflow-hidden rounded-lg bg-[#F1F5F9]">
                        <div class="w-full rounded-lg bg-teal-600/80"
                            style="height: {{ $month['billed'] > 0 ? max(6, round($month['billed'] / $maxBilled * 100)) : 2 }}%"></div>
                    </div>
                    <p class="mt-2 text-[13px] font-bold text-[#0F172A]">@rupiah($month['billed'])</p>
                    <p class="text-[11px] text-[#94A3B8]">{{ number_format($month['count'], 0, ',', '.') }} tagihan</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
        <div class="border-b border-[#EEF2F1] px-5 py-4">
            <p class="font-bold text-[#0F172A]">Rincian per Kondominan</p>
            <p class="text-[13px] text-[#64748B]">Jumlah pengguna, warga, dan transaksi setiap klien.</p>
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty-state icon="building-2" title="Belum ada condominan"
                subtitle="Daftarkan condominan pertama untuk mulai melihat statistik platform." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-[14px]">
                    <thead class="bg-[#F8FAFC] text-left text-[12px] uppercase tracking-wide text-[#64748B]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Kondominan</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold">Blok</th>
                            <th class="px-3 py-3 text-right font-semibold">Rumah</th>
                            <th class="px-3 py-3 text-right font-semibold">Warga</th>
                            <th class="px-3 py-3 text-right font-semibold">User</th>
                            <th class="px-3 py-3 text-right font-semibold">Tagihan</th>
                            <th class="px-3 py-3 text-right font-semibold">Ditagihkan</th>
                            <th class="px-5 py-3 text-right font-semibold">Tunggakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEF2F1]">
                        @foreach ($rows as $row)
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
                                <td class="px-3 py-3 text-right">{{ number_format($row['blocks'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($row['houses'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($row['residents'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($row['users'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($row['billings'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right font-medium">@rupiah($row['billed'])</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($row['outstanding'] > 0)
                                        <span class="font-semibold text-rose-600">@rupiah($row['outstanding'])</span>
                                    @else
                                        <span class="text-[#94A3B8]">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Kondominan Terbesar (Warga)</p>
            <ol class="mt-4 space-y-3 text-[14px]">
                @forelse ($topByResidents as $top)
                    <li class="flex items-center justify-between gap-3">
                        <span class="truncate text-[#475569]">{{ $top['estate']->name }}</span>
                        <span class="font-semibold text-[#0F172A]">{{ number_format($top['residents'], 0, ',', '.') }}</span>
                    </li>
                @empty
                    <li class="text-[#94A3B8]">Belum ada data.</li>
                @endforelse
            </ol>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Kondominan Terbesar (Transaksi)</p>
            <ol class="mt-4 space-y-3 text-[14px]">
                @forelse ($topByBilled as $top)
                    <li class="flex items-center justify-between gap-3">
                        <span class="truncate text-[#475569]">{{ $top['estate']->name }}</span>
                        <span class="font-semibold text-[#0F172A]">@rupiah($top['billed'])</span>
                    </li>
                @empty
                    <li class="text-[#94A3B8]">Belum ada data.</li>
                @endforelse
            </ol>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="font-bold text-[#0F172A]">Kondominan Terbaru</p>
            <ol class="mt-4 space-y-3 text-[14px]">
                @forelse ($recentEstates as $new)
                    <li class="flex items-center justify-between gap-3">
                        <span class="truncate text-[#475569]">{{ $new->name }}</span>
                        <span class="shrink-0 text-[12px] text-[#94A3B8]">{{ $new->created_at?->format('d M Y') }}</span>
                    </li>
                @empty
                    <li class="text-[#94A3B8]">Belum ada data.</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
