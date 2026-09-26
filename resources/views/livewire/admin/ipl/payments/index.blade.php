@php use App\Support\Currency; @endphp

<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Menunggu Verifikasi</p>
            <p class="mt-1 text-2xl font-bold {{ $summary['pending'] > 0 ? 'text-amber-600' : '' }}">{{ $summary['pending'] }}</p>
            <p class="text-[13px] text-[#64748B]">@rupiah($summary['pendingAmount'])</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Terverifikasi Bulan Ini</p>
            <p class="mt-1 text-2xl font-bold text-emerald-700">@rupiah($summary['verifiedThisMonth'])</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Total Ditampilkan</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($payments->total(), 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="space-y-2">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari no. pembayaran / invoice / warga..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
        <select wire:model.live="statusFilter" class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px] md:max-w-xs">
            <option value="">Semua Status</option>
            <option value="pending">Menunggu Verifikasi</option>
            <option value="verified">Terverifikasi</option>
            <option value="rejected">Ditolak</option>
        </select>
    </div>

    {{-- Mobile list --}}
    <div class="space-y-2 md:hidden">
        @forelse ($payments as $payment)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-[12px] text-[#64748B]">{{ $payment->payment_number }}</p>
                        <p class="mt-0.5 text-[18px] font-bold">@rupiah($payment->amount)</p>
                        <p class="text-[13px] text-[#64748B]">{{ $payment->resident?->name ?? '-' }} · {{ $payment->payment_date?->format('d/m/Y') }}</p>
                        <p class="text-[13px] text-[#64748B]">Invoice {{ $payment->billing?->invoice_number ?? '-' }}</p>
                        <p class="text-[13px] text-[#64748B]">Rumah {{ $payment->billing?->house?->fullLabel() ?? '-' }} · {{ $payment->methodLabel() }}</p>
                        @if ($payment->rejection_reason)
                            <p class="mt-1 text-[13px] text-red-600">Alasan: {{ $payment->rejection_reason }}</p>
                        @endif
                    </div>
                    <x-ui.badge color="{{ $payment->statusColor() }}">{{ $payment->statusLabel() }}</x-ui.badge>
                </div>

                @if ($payment->hasProof())
                    <a href="{{ $payment->proofUrl() }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[13px] font-semibold underline">
                        <i data-lucide="image" class="h-3.5 w-3.5"></i> Lihat bukti pembayaran
                    </a>
                @endif

                @if ($rejectingId === $payment->id)
                    <div class="mt-3 rounded-xl bg-red-50 p-3">
                        <x-ui.field label="Alasan Penolakan" :error="$errors->first('rejection_reason')">
                            <input wire:model="rejection_reason" placeholder="Contoh: bukti tidak jelas"
                                class="min-h-[44px] w-full rounded-xl border border-red-200 px-3 text-[15px] outline-none">
                        </x-ui.field>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button wire:click="reject" wire:loading.attr="disabled" class="min-h-[44px] rounded-xl bg-red-600 font-semibold text-white">Tolak</button>
                            <button wire:click="cancelReject" class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white font-semibold">Batal</button>
                        </div>
                    </div>
                @elseif ($payment->status === 'pending')
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <button wire:click="startVerify({{ $payment->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-emerald-200 text-[14px] font-semibold text-emerald-700"><i data-lucide="check" class="h-4 w-4"></i> Verifikasi</button>
                        <button wire:click="startReject({{ $payment->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-amber-200 text-[14px] font-semibold text-amber-700"><i data-lucide="x" class="h-4 w-4"></i> Tolak</button>
                        <button wire:click="delete({{ $payment->id }})" wire:confirm="Hapus pembayaran ini?" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                    </div>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="receipt" title="Belum ada data pembayaran" subtitle="Pembayaran dari warga akan muncul di sini." />
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">No. Pembayaran</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Warga</th>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Metode</th>
                    <th class="px-4 py-3">Nominal</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-mono text-[13px]">{{ $payment->payment_number }}</td>
                        <td class="px-4 py-3">
                            @if ($payment->billing)
                                <a href="{{ route('admin.ipl.billings.show', $payment->billing) }}" wire:navigate class="font-semibold underline">
                                    {{ $payment->billing->invoice_number }}
                                </a>
                                <p class="text-[12px] text-[#64748B]">Rumah {{ $payment->billing->house?->fullLabel() ?? '-' }}</p>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $payment->resident?->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $payment->methodLabel() }}</td>
                        <td class="px-4 py-3 font-semibold">@rupiah($payment->amount)</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $payment->statusColor() }}">{{ $payment->statusLabel() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($payment->status === 'pending')
                                <button wire:click="startVerify({{ $payment->id }})" class="font-semibold text-emerald-700">Verifikasi</button>
                                <button wire:click="startReject({{ $payment->id }})" class="ml-3 font-semibold text-amber-700">Tolak</button>
                                <button wire:click="delete({{ $payment->id }})" wire:confirm="Hapus pembayaran ini?" class="ml-3 font-semibold text-red-600">Hapus</button>
                            @else
                                <span class="text-[#64748B]">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-[#64748B]">Belum ada data pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $payments->links() }}</div>

    {{-- Popup verifikasi: pilih kas tujuan pemasukan --}}
    @if ($verifyingId)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
            <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold">Verifikasi Pembayaran</p>
                        <p class="text-[13px] text-[#64748B]">Dana akan otomatis dicatat sebagai kas masuk ke kas yang dipilih.</p>
                    </div>
                    <button wire:click="cancelVerify" class="flex h-9 w-9 items-center justify-center rounded-xl border"><i data-lucide="x" class="h-4 w-4"></i></button>
                </div>

                <form wire:submit="confirmVerify" class="mt-3 space-y-3">
                    <x-ui.field label="Masuk ke Kas" :error="$errors->first('cash_account_id')">
                        <select wire:model="cash_account_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            <option value="">— Tanpa pencatatan kas —</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} (Saldo @rupiah($account->currentBalance()))</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    @if ($accounts->isEmpty())
                        <p class="rounded-xl bg-amber-50 p-3 text-[13px] text-amber-700">Belum ada kas terdaftar. Verifikasi tetap bisa dilanjutkan tanpa pencatatan kas.</p>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="cancelVerify" class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white font-semibold">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="min-h-[44px] rounded-xl bg-emerald-600 font-semibold text-white disabled:opacity-60">Verifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
</div>