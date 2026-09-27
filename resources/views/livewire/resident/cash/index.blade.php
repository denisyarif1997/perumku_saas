@php use App\Support\Currency; @endphp

<div class="space-y-4">
    {{-- Ringkasan kas --}}
    <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-teal-600 to-teal-800 p-5 text-white shadow-xl shadow-teal-900/20">
        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-8 right-12 h-14 w-14 rounded-full bg-amber-300/20"></div>
        <p class="text-[13px] font-medium text-teal-100">Total Saldo Kas</p>
        <p class="mt-1 text-[30px] font-bold">@rupiah($totalBalance)</p>
        <p class="text-[13px] text-teal-100">Saldo kas bersama condominium</p>
        <div class="mt-3 grid grid-cols-2 gap-2 text-[13px]">
            <div class="rounded-2xl bg-white/10 p-3">
                <p class="text-teal-100">Masuk {{ Currency::monthName(now()->month) }}</p>
                <p class="font-bold">@rupiah($inThisMonth)</p>
            </div>
            <div class="rounded-2xl bg-white/10 p-3">
                <p class="text-teal-100">Keluar {{ Currency::monthName(now()->month) }}</p>
                <p class="font-bold">@rupiah($outThisMonth)</p>
            </div>
        </div>
    </div>

    {{-- Daftar kas beserta saldonya --}}
    <div class="rounded-[24px] bg-white p-5 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
        <p class="text-[15px] font-bold text-[#134E4A]">Saldo per Kas</p>

        <div class="mt-3 space-y-2">
            @forelse ($accounts as $account)
                @php $balance = $account->currentBalance(); @endphp
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#F6F8F7] p-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-[14px] font-bold text-slate-900">{{ $account->name }}</p>
                        <p class="text-[12px] text-[#64748B]">
                            {{ $account->type === 'bank' ? 'Rekening Bank' : 'Kas Tunai' }}
                            @if ($account->housing_estate_id === null)
                                · Bersama
                            @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-[15px] font-bold {{ $balance < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                        @rupiah($balance)
                    </p>
                </div>
            @empty
                <x-ui.empty-state icon="wallet" title="Belum ada kas"
                    subtitle="Kas akan tampil setelah pengelola menambahkannya." />
            @endforelse
        </div>
    </div>

    {{-- Riwayat mutasi (tanpa nama warga) --}}
    <div class="space-y-3">
        <p class="px-1 text-[15px] font-bold text-[#134E4A]">Riwayat Kas</p>

        @forelse ($transactions as $transaction)
            @php
                $masuk = $transaction->type === 'in';
                $transfer = $transaction->type === 'transfer' || $transaction->type === 'reversal';
                $warna = $transfer ? 'text-slate-700' : ($masuk ? 'text-emerald-700' : 'text-red-600');
            @endphp
            <div class="rounded-[22px] bg-white p-4 shadow-[0_8px_30px_-6px_rgba(19,78,74,0.12)]">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[14px] font-bold text-slate-900">{{ $transaction->typeLabel() }}</p>
                        <p class="mt-0.5 truncate text-[13px] text-[#64748B]">
                            {{ $transaction->account?->name ?? '-' }}
                            @if ($transaction->type === 'transfer' && $transaction->destinationAccount)
                                → {{ $transaction->destinationAccount->name }}
                            @endif
                        </p>
                        <p class="text-[12px] text-[#64748B]">
                            {{ $transaction->transaction_date?->format('d/m/Y') ?? '-' }}
                            @if ($transaction->category)
                                · {{ $transaction->category }}
                            @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-[16px] font-bold {{ $warna }}">
                        {{ $transfer ? '' : ($masuk ? '+' : '−') }}@rupiah($transaction->amount)
                    </p>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="history" title="Belum ada mutasi kas"
                subtitle="Riwayat kas masuk dan keluar akan tampil di sini." />
        @endforelse
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
