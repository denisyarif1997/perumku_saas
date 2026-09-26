<?php

namespace App\Livewire\Resident\Ipl;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Payment;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Billing $billing;

    public string $amount = '';

    public string $payment_method = 'transfer';

    public string $payment_date = '';

    public string $reference_number = '';

    public string $notes = '';

    /** @var TemporaryUploadedFile|null */
    public $proof = null;

    public function mount(Billing $billing): void
    {
        abort_unless(auth()->user()->resident_id, 403, 'Akun ini tidak terhubung dengan data warga.');

        // Warga hanya boleh mengakses tagihan milik huniannya.
        abort_unless($this->belongsToResident($billing), 403);

        $this->billing = $billing;
        $this->payment_date = now()->toDateString();
        $this->amount = (string) $billing->remaining();
    }

    protected function belongsToResident(Billing $billing): bool
    {
        $residentId = (int) auth()->user()->resident_id;

        if ((int) $billing->resident_id === $residentId) {
            return true;
        }

        return $billing->house()->whereHas('houseResidents', fn (Builder $q) => $q
            ->where('resident_id', $residentId)
            ->where('status', 'active'))->exists();
    }

    public function submitPayment(): void
    {
        abort_unless(auth()->user()->resident_id, 403);
        abort_unless($this->belongsToResident($this->billing), 403);

        if (! in_array($this->billing->status, ['unpaid', 'partial'], true)) {
            session()->flash('error', 'Tagihan ini tidak dapat dibayar lagi.');

            return;
        }

        $data = $this->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:cash,transfer,qris,other'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'image', 'max:2048'],
        ], [
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.min' => 'Nominal pembayaran harus lebih dari 0.',
            'payment_date.before_or_equal' => 'Tanggal pembayaran tidak boleh melebihi hari ini.',
            'proof.image' => 'Bukti pembayaran harus berupa gambar.',
            'proof.max' => 'Ukuran bukti pembayaran maksimal 2 MB.',
        ]);

        $remaining = $this->billing->remaining();

        if ((float) $data['amount'] > $remaining) {
            $this->addError('amount', 'Nominal melebihi sisa tagihan ('.Currency::rupiah($remaining).').');

            return;
        }

        // Simpan bukti bayar sebagai BLOB di database (gambar, maks 2 MB).
        $proofBlob = null;
        $proofMime = null;
        $proofName = null;
        $proofSize = null;

        if ($this->proof) {
            $proofBlob = file_get_contents($this->proof->getRealPath());
            $proofMime = $this->proof->getMimeType() ?: 'image/jpeg';
            $proofName = $this->proof->getClientOriginalName();
            $proofSize = $this->proof->getSize();

            // Validasi ganda: hanya gambar & maks 2 MB (2 * 1024 * 1024 byte).
            if (! str_starts_with($proofMime, 'image/')) {
                $this->addError('proof', 'Bukti pembayaran harus berupa gambar.');

                return;
            }

            if ($proofSize > 2 * 1024 * 1024 || strlen((string) $proofBlob) > 2 * 1024 * 1024) {
                $this->addError('proof', 'Ukuran bukti pembayaran maksimal 2 MB.');

                return;
            }
        }

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber($this->billing),
            'billing_id' => $this->billing->id,
            'housing_estate_id' => $this->billing->housing_estate_id,
            'resident_id' => auth()->user()->resident_id,
            'user_id' => auth()->id(),
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?: null,
            'proof' => null,
            'proof_blob' => $proofBlob,
            'proof_mime' => $proofMime,
            'proof_name' => $proofName,
            'proof_size' => $proofSize,
            'status' => 'pending',
            'notes' => $data['notes'] ?: null,
        ]);

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'create', 'module' => 'payments',
            'subject_type' => Payment::class, 'subject_id' => $payment->id,
            'description' => 'Konfirmasi pembayaran tagihan '.$this->billing->invoice_number,
            // Jangan simpan BLOB ke log (bisa 2 MB) — hanya metadata bukti.
            'new_values' => Arr::except($payment->fresh()->toArray(), ['proof_blob']),
        ]);

        $this->reset(['amount', 'reference_number', 'notes', 'proof']);
        $this->payment_date = now()->toDateString();
        $this->amount = (string) $this->billing->remaining();

        session()->flash('success', 'Konfirmasi pembayaran terkirim. Menunggu verifikasi pengelola.');
    }

    public function deletePayment(int $id): void
    {
        $payment = $this->billing->payments()->findOrFail($id);

        $this->authorize('delete', $payment);

        $payment->delete();

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'delete', 'module' => 'payments',
            'subject_type' => Payment::class, 'subject_id' => $id,
            'description' => 'Membatalkan konfirmasi pembayaran '.$payment->payment_number,
        ]);

        session()->flash('success', 'Konfirmasi pembayaran dibatalkan.');
    }

    #[Layout('layouts.resident', ['title' => 'Detail Tagihan'])]
    public function render()
    {
        return view('livewire.resident.ipl.show', [
            'billing' => $this->billing->load(['house.block', 'iplRate', 'waterRate']),
            'payments' => $this->billing->payments()->orderByDesc('id')->get(),
            'periodLabel' => $this->billing->periodLabel(),
            'remaining' => $this->billing->remaining(),
            'hasPendingPayment' => $this->billing->payments()->where('status', 'pending')->exists(),
        ]);
    }
}
