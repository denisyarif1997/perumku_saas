<?php

namespace App\Livewire\Admin\Ipl\Billings;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public Billing $billing;

    public string $payment_amount = '';

    public string $payment_method = 'cash';

    public string $payment_date = '';

    public string $reference_number = '';

    public string $payment_notes = '';

    public ?int $rejectingId = null;

    public string $rejection_reason = '';

    public ?int $verifyingId = null;

    public string $cash_account_id = '';

    public string $payment_cash_account_id = '';

    public function mount(Billing $billing): void
    {
        $this->authorize('view', $billing);
        abort_unless(
            auth()->user()->hasPermission('manage-billing') || auth()->user()->hasPermission('verify-payment'),
            403
        );

        $this->billing = $billing;
        $this->payment_date = now()->toDateString();
        $this->payment_amount = (string) $billing->remaining();
    }

    /**
     * Catat pembayaran manual (tunai/transfer) yang langsung terverifikasi.
     */
    public function recordPayment(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-payment'), 403);

        if ($this->billing->status === 'cancelled') {
            session()->flash('error', 'Tagihan sudah dibatalkan.');

            return;
        }

        $data = $this->validate([
            'payment_amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:cash,transfer,qris,other'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
            'payment_cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
        ], [
            'payment_amount.required' => 'Nominal pembayaran wajib diisi.',
            'payment_amount.min' => 'Nominal pembayaran harus lebih dari 0.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
        ]);

        $billing = $this->billing;
        $account = $data['payment_cash_account_id'] ? CashAccount::findOrFail($data['payment_cash_account_id']) : null;

        DB::transaction(function () use ($billing, $data, $account) {
            $payment = Payment::create([
                'payment_number' => Payment::generateNumber($billing),
                'billing_id' => $billing->id,
                'housing_estate_id' => $billing->housing_estate_id,
                'resident_id' => $billing->resident_id,
                'user_id' => auth()->id(),
                'amount' => $data['payment_amount'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?: null,
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $data['payment_notes'] ?: 'Dicatat manual oleh pengelola.',
            ]);

            $cashEntry = null;
            if ($account) {
                $cashEntry = CashTransaction::recordForPayment($payment, $account, (int) auth()->id());

                if ($cashEntry) {
                    ActivityLog::record([
                        'user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_transactions',
                        'subject_type' => CashTransaction::class, 'subject_id' => $cashEntry->id,
                        'description' => 'Kas masuk otomatis dari pembayaran '.$payment->payment_number.' ke '.$account->name,
                        'new_values' => $cashEntry->toArray(),
                    ]);
                }
            }

            $billing->syncPaymentStatus();

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'create', 'module' => 'payments',
                'subject_type' => Billing::class, 'subject_id' => $billing->id,
                'description' => 'Mencatat pembayaran IPL '.$billing->invoice_number
                    .($account ? ' (masuk kas '.$account->name.')' : ' (tanpa pencatatan kas)'),
                'new_values' => $billing->fresh()->toArray(),
            ]);
        });

        $this->billing->refresh();
        $this->reset(['payment_amount', 'reference_number', 'payment_notes', 'payment_cash_account_id']);
        $this->payment_amount = (string) $this->billing->remaining();
        $this->payment_date = now()->toDateString();

        session()->flash('success', 'Pembayaran berhasil dicatat.');
    }

    /**
     * Buka popup verifikasi: pilih kas tujuan pemasukan.
     */
    public function startVerify(int $id): void
    {
        $this->authorize('verify', Payment::class);

        $payment = Payment::where('billing_id', $this->billing->id)->findOrFail($id);

        if ($payment->status === 'verified') {
            session()->flash('error', 'Pembayaran sudah terverifikasi.');

            return;
        }

        $this->verifyingId = $id;
        $this->cash_account_id = '';
        $this->resetValidation('cash_account_id');
    }

    public function cancelVerify(): void
    {
        $this->reset(['verifyingId', 'cash_account_id']);
        $this->resetValidation('cash_account_id');
    }

    public function confirmVerify(): void
    {
        $this->authorize('verify', Payment::class);

        if (! $this->verifyingId) {
            return;
        }

        $data = $this->validate([
            'cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
        ]);

        $payment = Payment::with(['billing', 'resident'])->where('billing_id', $this->billing->id)->findOrFail($this->verifyingId);

        if ($payment->status === 'verified') {
            session()->flash('error', 'Pembayaran sudah terverifikasi.');
            $this->cancelVerify();

            return;
        }

        $account = $data['cash_account_id'] ? CashAccount::findOrFail($data['cash_account_id']) : null;

        DB::transaction(function () use ($payment, $account) {
            $payment->update([
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $cashEntry = null;
            if ($account) {
                $cashEntry = CashTransaction::recordForPayment($payment, $account, (int) auth()->id());

                if ($cashEntry) {
                    ActivityLog::record([
                        'user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_transactions',
                        'subject_type' => CashTransaction::class, 'subject_id' => $cashEntry->id,
                        'description' => 'Kas masuk otomatis dari pembayaran '.$payment->payment_number.' ke '.$account->name,
                        'new_values' => $cashEntry->toArray(),
                    ]);
                }
            }

            $this->billing->syncPaymentStatus();

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'approve', 'module' => 'payments',
                'subject_type' => Payment::class, 'subject_id' => $payment->id,
                'description' => 'Memverifikasi pembayaran '.$payment->payment_number
                    .($account ? ' (masuk kas '.$account->name.')' : ' (tanpa pencatatan kas)'),
                'new_values' => $payment->fresh()->toArray(),
            ]);
        });

        $this->billing->refresh();
        $this->payment_amount = (string) $this->billing->remaining();
        $this->cancelVerify();

        session()->flash('success', 'Pembayaran '.$payment->payment_number.' berhasil diverifikasi.');
    }

    public function startReject(int $id): void
    {
        $this->authorize('reject', Payment::class);

        $this->rejectingId = $id;
        $this->rejection_reason = '';
        $this->resetValidation('rejection_reason');
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'rejection_reason']);
    }

    public function rejectPayment(): void
    {
        $this->authorize('reject', Payment::class);

        if (! $this->rejectingId) {
            return;
        }

        $data = $this->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $payment = Payment::where('billing_id', $this->billing->id)->findOrFail($this->rejectingId);

        DB::transaction(function () use ($payment, $data) {
            $payment->update([
                'status' => 'rejected',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);

            // Balikkan kas masuk bila pembayaran sebelumnya sudah dicatat ke kas.
            CashTransaction::reverseForPayment($payment, (int) auth()->id());

            $this->billing->syncPaymentStatus();

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'reject', 'module' => 'payments',
                'subject_type' => Payment::class, 'subject_id' => $payment->id,
                'description' => 'Menolak pembayaran '.$payment->payment_number.' — '.$data['rejection_reason'],
                'new_values' => $payment->fresh()->toArray(),
            ]);
        });

        $this->billing->refresh();
        $this->payment_amount = (string) $this->billing->remaining();
        $number = $payment->payment_number;
        $this->cancelReject();

        session()->flash('success', 'Pembayaran '.$number.' ditolak.');
    }

    public function deletePayment(int $id): void
    {
        $payment = Payment::where('billing_id', $this->billing->id)->findOrFail($id);

        $this->authorize('delete', $payment);

        if ($payment->status === 'verified') {
            session()->flash('error', 'Pembayaran terverifikasi tidak bisa dihapus.');

            return;
        }

        $number = $payment->payment_number;

        DB::transaction(function () use ($payment) {
            $old = $payment->toArray();
            $payment->delete();

            $this->billing->syncPaymentStatus();

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'delete', 'module' => 'payments',
                'subject_type' => Payment::class, 'subject_id' => $payment->id,
                'description' => 'Menghapus pembayaran '.$payment->payment_number,
                'old_values' => $old,
            ]);
        });

        $this->billing->refresh();

        session()->flash('success', 'Pembayaran '.$number.' berhasil dihapus.');
    }

    public function cancelBilling(): void
    {
        $this->authorize('update', $this->billing);

        $verifiedPayments = $this->billing->verifiedPayments()->get();

        DB::transaction(function () use ($verifiedPayments) {
            // Balikkan semua kas masuk yang berasal dari pembayaran tagihan ini.
            foreach ($verifiedPayments as $payment) {
                $reversed = CashTransaction::reverseForPayment($payment, (int) auth()->id());

                if ($reversed > 0) {
                    ActivityLog::record([
                        'user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_transactions',
                        'subject_type' => Payment::class, 'subject_id' => $payment->id,
                        'description' => 'Pembalikan kas masuk pembayaran '.$payment->payment_number.' (tagihan dibatalkan)',
                    ]);
                }
            }

            $this->billing->update(['status' => 'cancelled']);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'billings',
                'subject_type' => Billing::class, 'subject_id' => $this->billing->id,
                'description' => 'Membatalkan tagihan '.$this->billing->invoice_number
                    .($verifiedPayments->isNotEmpty() ? ' (pembayaran dikembalikan ke kas)' : ''),
                'new_values' => $this->billing->fresh()->toArray(),
            ]);
        });

        $this->billing->refresh();

        session()->flash('success', 'Tagihan dibatalkan. Pembayaran yang sudah masuk kas telah dibalikkan.');
    }

    public function activeBilling(): void
    {
        $this->billing->update(['status' => 'unpaid']);

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'update', 'module' => 'billings',
            'subject_type' => Billing::class, 'subject_id' => $this->billing->id,
            'description' => 'Aktivasi tagihan yang di batalkan '.$this->billing->invoice_number,
            'new_values' => $this->billing->fresh()->toArray(),
        ]);

        $this->billing->refresh();

        session()->flash('success', 'Tagihan aktif kembali.');
    }

    #[Layout('layouts.admin', ['title' => 'Detail Tagihan IPL'])]
    public function render()
    {
        return view('livewire.admin.ipl.billings.show', [
            'billing' => $this->billing->load(['house.block', 'resident', 'iplRate', 'waterRate']),
            'payments' => $this->billing->payments()
                ->with(['verifier', 'resident'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->get(),
            'cashAccounts' => CashAccount::active()->orderBy('name')->get(),
        ]);
    }
}
