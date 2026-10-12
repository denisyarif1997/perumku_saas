<div class="space-y-4">
    @if (session('status'))
        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <p class="text-[13px] text-[#64748B]">Perumahan {{ $estate->code }}</p>
                <h2 class="text-lg font-bold text-[#134E4A]">{{ $estate->name }}</h2>
                <p class="mt-1 text-[14px]">
                    Status:
                    @if ($subscription->status === 'trial')
                        <span class="font-semibold text-sky-700">Trial — sisa {{ $daysLeft }} hari</span>
                        <span class="text-[#64748B]">(berakhir {{ $subscription->trial_ends_at?->format('d M Y') }})</span>
                    @elseif ($subscription->status === 'active')
                        <span class="font-semibold text-emerald-700">Aktif</span>
                        <span class="text-[#64748B]">s/d {{ $subscription->current_period_end?->format('d M Y') }}</span>
                    @elseif ($subscription->status === 'past_due')
                        <span class="font-semibold text-amber-700">Menunggak</span>
                    @else
                        <span class="font-semibold text-red-700">{{ ucfirst($subscription->status) }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="payBase" class="min-h-[48px] rounded-xl bg-teal-700 px-4 font-semibold text-white">Bayar Paket</button>
                @if ($overage > 0)
                    <button wire:click="payOverage" class="min-h-[48px] rounded-xl border border-amber-300 bg-amber-50 px-4 font-semibold text-amber-800">Bayar Kelebihan ({{ $overage }})</button>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[12px] uppercase tracking-wide text-[#64748B]">User aktif</p>
                <p class="text-xl font-bold">{{ $activeUsers }} <span class="text-[13px] font-normal text-[#64748B]">/ {{ $included }} termasuk</span></p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[12px] uppercase tracking-wide text-[#64748B]">Paket / bulan</p>
                <p class="text-xl font-bold">Rp {{ number_format($monthlyPrice, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[12px] uppercase tracking-wide text-[#64748B]">Kelebihan / user / bulan</p>
                <p class="text-xl font-bold">Rp {{ number_format($overagePerUser, 0, ',', '.') }}</p>
            </div>
        </div>

        @if ($overage > 0)
            <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-900">
                Kuota {{ $included }} user tercapai — {{ $overage }} user kelebihan dikenai Rp {{ number_format($overagePerUser, 0, ',', '.') }}/user/bulan.
                Tambah user baru dibuka setelah invoice overage lunas.
            </p>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#EEF2F1] bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-[14px]">
                <thead>
                    <tr class="border-b border-[#EEF2F1] bg-[#F8FAFC] text-[12px] uppercase tracking-wide text-[#64748B]">
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Periode</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        <tr class="border-b border-[#F1F5F9] last:border-0" wire:key="inv-{{ $inv->id }}">
                            <td class="px-4 py-3">
                                <p class="font-mono font-semibold">{{ $inv->number }}</p>
                                <p class="text-[12px] text-[#64748B]">{{ $inv->type === 'base' ? 'Paket bulanan' : 'Kelebihan '.$inv->overage_users.' user' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $inv->period_label ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3"><x-ui.badge :color="$inv->status === 'paid' ? 'green' : ($inv->status === 'pending' ? 'amber' : 'red')">{{ $inv->status }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-right">
                                @if ($inv->status !== 'paid')
                                    <button wire:click="pay({{ $inv->id }})" class="font-semibold text-teal-700">Bayar</button>
                                @else
                                    <span class="text-[13px] text-[#94A3B8]">lunas {{ $inv->paid_at?->format('d M Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-[#64748B]">Belum ada invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $invoices->links() }}
</div>
