<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    <a href="{{ route('admin.ipl.billings.index') }}" wire:navigate class="inline-flex items-center gap-2 text-[14px] font-semibold text-[#64748B]">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali ke daftar tagihan
    </a>

    {{-- Ringkasan tagihan --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-mono text-[13px] text-[#64748B]">{{ $billing->invoice_number }}</p>
                <p class="mt-1 text-lg font-bold">Rumah {{ $billing->house?->fullLabel() ?? '-' }}</p>
                <p class="text-[14px] text-[#64748B]">{{ $billing->resident?->name ?? 'Tanpa penghuni' }}</p>
            </div>
            <x-ui.badge color="{{ $billing->statusColor() }}">{{ $billing->statusLabel() }}</x-ui.badge>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-[14px]">
            <div>
                <dt class="text-[#64748B]">Periode</dt>
                <dd class="font-semibold">{{ $billing->periodLabel() }}</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Tipe</dt>
                <dd class="font-semibold">{{ $billing->typeLabel() }}</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Tarif</dt>
                <dd class="font-semibold">{{ $billing->isWater() ? ($billing->waterRate?->name ?? 'Tarif air tidak tercatat') : ($billing->iplRate?->name ?? 'Tarif tidak tercatat') }}</dd>
            </div>
            @if ($billing->isWater())
                <div>
                    <dt class="text-[#64748B]">Meter Awal</dt>
                    <dd class="font-semibold">{{ $billing->meter_start }} m³</dd>
                </div>
                <div>
                    <dt class="text-[#64748B]">Meter Akhir</dt>
                    <dd class="font-semibold">{{ $billing->meter_end }} m³</dd>
                </div>
                <div>
                    <dt class="text-[#64748B]">Pemakaian</dt>
                    <dd class="font-semibold">{{ $billing->usage_m3 }} m³</dd>
                </div>
            @endif
            <div>
                <dt class="text-[#64748B]">Jatuh Tempo</dt>
                <dd class="font-semibold">{{ $billing->due_date?->format('d/m/Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Nominal Dasar</dt>
                <dd class="font-semibold">@rupiah($billing->amount)</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Diskon</dt>
                <dd class="font-semibold">@rupiah($billing->discount)</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Total Tagihan</dt>
                <dd class="font-semibold">@rupiah($billing->total)</dd>
            </div>
            <div>
                <dt class="text-[#64748B]">Sudah Dibayar</dt>
                <dd class="font-semibold text-emerald-700">@rupiah($billing->paid_amount)</dd>
            </div>
        </dl>

        <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 p-3">
            <span class="text-[14px] font-semibold">Sisa Tagihan</span>
            <span class="text-xl font-bold {{ $billing->remaining() > 0 ? 'text-red-600' : 'text-emerald-700' }}">@rupiah($billing->remaining())</span>
        </div>

        @if ($billing->notes)
            <p class="mt-3 text-[13px] text-[#64748B]">Catatan: {{ $billing->notes }}</p>
        @endif

        @if ($billing->status !== 'cancelled')
            <button wire:click="cancelBilling" wire:confirm="Batalkan tagihan {{ $billing->invoice_number }}?"
                class="mt-4 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-red-200 font-semibold text-red-700">
                <i data-lucide="ban" class="h-4 w-4"></i> Batalkan Tagihan
            </button>
        @endif

         @if ($billing->status == 'cancelled')
            <button wire:click="activeBilling" wire:confirm="Aktifkan tagihan {{ $billing->invoice_number }}?"
               class="mt-4 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-[#16A34A] font-semibold text-green transition hover:bg-[#15803D]">
    <i data-lucide="check-circle-2" class="h-4 w-4"></i> Aktifkan Tagihan
            </button>
        @endif
    </div>

    {{-- Form catat pembayaran --}}
    @if ($billing->status !== 'cancelled')
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="font-bold">Catat Pembayaran</p>
            <p class="mt-1 text-[14px] text-[#64748B]">Pembayaran yang dicatat di sini langsung berstatus terverifikasi.</p>

            <form wire:submit="recordPayment" class="mt-3 grid gap-3 md:grid-cols-2">
                <x-ui.field label="Nominal (Rp)" :error="$errors->first('payment_amount')">
                    <input wire:model="payment_amount" type="number" min="1" step="1" inputmode="numeric"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                </x-ui.field>

                <x-ui.field label="Metode" :error="$errors->first('payment_method')">
                    <select wire:model="payment_method" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                        <option value="cash">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                        <option value="other">Lainnya</option>
                    </select>
                </x-ui.field>

                <x-ui.field label="Tanggal Bayar" :error="$errors->first('payment_date')">
                    <input wire:model="payment_date" type="date"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                </x-ui.field>

                <x-ui.field label="No. Referensi (opsional)" :error="$errors->first('reference_number')">
                    <input wire:model="reference_number" placeholder="Contoh: TRF-889213"
                        class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                </x-ui.field>

                <div class="md:col-span-2">
                    <x-ui.field label="Catatan (opsional)" :error="$errors->first('payment_notes')">
                        <input wire:model="payment_notes" placeholder="Catatan internal"
                            class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
                    </x-ui.field>
                </div>

                <div class="md:col-span-2">
                    <x-ui.field label="Masuk ke Kas" :error="$errors->first('payment_cash_account_id')">
                        <select wire:model="payment_cash_account_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                            <option value="">— Tanpa pencatatan kas —</option>
                            @foreach ($cashAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} (Saldo @rupiah($account->currentBalance()))</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <p class="mt-1 text-[13px] text-[#64748B]">Dana akan otomatis tercatat sebagai kas masuk di kas yang dipilih.</p>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white disabled:opacity-60">
                        <i data-lucide="wallet" class="h-5 w-5"></i>
                        <span wire:loading.remove wire:target="recordPayment">Simpan Pembayaran</span>
                        <span wire:loading wire:target="recordPayment">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Riwayat pembayaran --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Riwayat Pembayaran</p>

        <div class="mt-3 space-y-2">
            @forelse ($payments as $payment)
                <div class="rounded-xl border border-[#E2E8F0] p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-[12px] text-[#64748B]">{{ $payment->payment_number }}</p>
                            <p class="mt-0.5 text-[16px] font-bold">@rupiah($payment->amount)</p>
                            <p class="text-[13px] text-[#64748B]">
                                {{ $payment->payment_date?->format('d/m/Y') }} · {{ $payment->methodLabel() }}@if ($payment->reference_number) · Ref {{ $payment->reference_number }}@endif
                            </p>
                            @if ($payment->verifier)
                                <p class="text-[12px] text-[#64748B]">Diproses oleh {{ $payment->verifier->name }}</p>
                            @endif
                            @if ($payment->rejection_reason)
                                <p class="text-[12px] text-red-600">Alasan: {{ $payment->rejection_reason }}</p>
                            @endif
                        </div>
                        <x-ui.badge color="{{ $payment->statusColor() }}">{{ $payment->statusLabel() }}</x-ui.badge>
                    </div>

                    @if ($payment->hasProof())
                        <a href="{{ $payment->proofUrl() }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[13px] font-semibold underline">
                            <i data-lucide="image" class="h-3.5 w-3.5"></i> Lihat bukti
                        </a>
                    @endif

                    @if ($rejectingId === $payment->id)
                        <div class="mt-3 rounded-xl bg-red-50 p-3">
                            <x-ui.field label="Alasan Penolakan" :error="$errors->first('rejection_reason')">
                                <input wire:model="rejection_reason" placeholder="Contoh: nominal tidak sesuai"
                                    class="min-h-[44px] w-full rounded-xl border border-red-200 px-3 text-[15px] outline-none">
                            </x-ui.field>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <button wire:click="rejectPayment" wire:loading.attr="disabled" class="min-h-[44px] rounded-xl bg-red-600 font-semibold text-white">Tolak</button>
                                <button wire:click="cancelReject" class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white font-semibold">Batal</button>
                            </div>
                        </div>
                    @elseif ($payment->status === 'pending')
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <button wire:click="startVerify({{ $payment->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-emerald-200 text-[14px] font-semibold text-emerald-700"><i data-lucide="check" class="h-4 w-4"></i> Verifikasi</button>
                            <button wire:click="startReject({{ $payment->id }})" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-amber-200 text-[14px] font-semibold text-amber-700"><i data-lucide="x" class="h-4 w-4"></i> Tolak</button>
                            <button wire:click="deletePayment({{ $payment->id }})" wire:confirm="Hapus pembayaran ini?" class="flex min-h-[44px] items-center justify-center gap-1 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                        </div>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="receipt" title="Belum ada pembayaran" subtitle="Belum ada pembayaran tercatat untuk tagihan ini." />
            @endforelse
        </div>
    </div>
</div>
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
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} (Saldo @rupiah($account->currentBalance()))</option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($cashAccounts->isEmpty())
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
