<?php

namespace App\Livewire\Admin\Ipl\Billings;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\HousingBlock;
use App\Models\Payment;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $blockFilter = '';

    public string $periodMonth = '';

    public string $periodYear = '';

    public ?int $markPaidId = null;

    public string $markPaid_cash_account_id = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()->hasPermission('manage-billing') || auth()->user()->hasPermission('verify-payment'),
            403
        );

        $this->periodYear = (string) now()->year;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'typeFilter', 'blockFilter', 'periodMonth', 'periodYear'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'blockFilter', 'periodMonth']);
        $this->periodYear = (string) now()->year;
        $this->resetPage();
    }

    /**
     * Buka popup tandai lunas: pilih kas tujuan pemasukan.
     */
    public function startMarkPaid(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-payment'), 403);

        $billing = Billing::findOrFail($id);

        if ($billing->status === 'paid') {
            session()->flash('error', 'Tagihan '.$billing->invoice_number.' sudah lunas.');

            return;
        }

        if ($billing->remaining() <= 0) {
            session()->flash('error', 'Tidak ada sisa tagihan yang perlu dibayar.');

            return;
        }

        $this->markPaidId = $id;
        $this->markPaid_cash_account_id = '';
        $this->resetValidation('markPaid_cash_account_id');
    }

    public function cancelMarkPaid(): void
    {
        $this->reset(['markPaidId', 'markPaid_cash_account_id']);
        $this->resetValidation('markPaid_cash_account_id');
    }

    public function confirmMarkPaid(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-payment'), 403);

        if (! $this->markPaidId) {
            return;
        }

        $data = $this->validate([
            'markPaid_cash_account_id' => ['required', 'exists:cash_accounts,id'],
        ], [
            'markPaid_cash_account_id.required' => 'Kas tujuan wajib dipilih.',
        ]);

        $billing = Billing::findOrFail($this->markPaidId);
        $amount = $billing->remaining();

        if ($amount <= 0) {
            session()->flash('error', 'Tidak ada sisa tagihan yang perlu dibayar.');
            $this->cancelMarkPaid();

            return;
        }

        $account = CashAccount::findOrFail($data['markPaid_cash_account_id']);

        DB::transaction(function () use ($billing, $amount, $account) {
            $payment = Payment::create([
                'payment_number' => Payment::generateNumber($billing),
                'billing_id' => $billing->id,
                'housing_estate_id' => $billing->housing_estate_id,
                'resident_id' => $billing->resident_id,
                'user_id' => auth()->id(),
                'amount' => $amount,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => 'Dicatat manual oleh pengelola.',
            ]);

            $cashEntry = CashTransaction::recordForPayment($payment, $account, (int) auth()->id());

            if ($cashEntry) {
                ActivityLog::record([
                    'user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_transactions',
                    'subject_type' => CashTransaction::class, 'subject_id' => $cashEntry->id,
                    'description' => 'Kas masuk otomatis dari pembayaran '.$payment->payment_number.' ke '.$account->name,
                    'new_values' => $cashEntry->toArray(),
                ]);
            }

            $billing->syncPaymentStatus();

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'billings',
                'subject_type' => Billing::class, 'subject_id' => $billing->id,
                'description' => 'Menandai lunas tagihan '.$billing->invoice_number.' (masuk kas '.$account->name.')',
                'new_values' => $billing->fresh()->toArray(),
            ]);
        });

        $this->cancelMarkPaid();

        session()->flash('success', 'Tagihan '.$billing->invoice_number.' ditandai lunas.');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $billing = Billing::with('verifiedPayments')->findOrFail($id);

        DB::transaction(function () use ($billing) {
            // Balikkan semua kas masuk dari pembayaran terverifikasi tagihan ini.
            $reversedCount = 0;
            foreach ($billing->verifiedPayments as $payment) {
                $reversedCount += CashTransaction::reverseForPayment($payment, (int) auth()->id());

                ActivityLog::record([
                    'user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_transactions',
                    'subject_type' => Payment::class, 'subject_id' => $payment->id,
                    'description' => 'Pembalikan kas masuk pembayaran '.$payment->payment_number.' (tagihan dihapus)',
                ]);
            }

            $old = $billing->toArray();
            $billing->delete();

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'delete', 'module' => 'billings',
                'subject_type' => Billing::class, 'subject_id' => $billing->id,
                'description' => 'Menghapus tagihan '.$billing->invoice_number
                    .($reversedCount > 0 ? ' (pembayaran dikembalikan ke kas)' : ''),
                'old_values' => $old,
            ]);
        });

        session()->flash('success', 'Tagihan '.$billing->invoice_number.' berhasil dihapus. Pembayaran yang sudah masuk kas telah dibalikkan.');
    }

    public function export()
    {
        abort_unless(auth()->user()->hasPermission('manage-billing') || auth()->user()->hasPermission('verify-payment'), 403);

        $billings = $this->filteredQuery()
            ->with(['house.block', 'resident', 'iplRate', 'waterRate'])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderBy('house_id')
            ->get();

        return response()->streamDownload(function () use ($billings) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Invoice', 'Tipe', 'Rumah', 'Blok', 'Penghuni', 'Periode', 'Tarif', 'Meter', 'Total', 'Dibayar', 'Sisa', 'Status', 'Jatuh Tempo']);

            foreach ($billings as $billing) {
                fputcsv($handle, [
                    $billing->invoice_number,
                    $billing->typeLabel(),
                    $billing->house?->fullLabel() ?? '-',
                    $billing->house?->block?->code ?? '-',
                    $billing->resident?->name ?? '-',
                    $billing->periodLabel(),
                    $billing->isWater() ? ($billing->waterRate?->name ?? '-') : ($billing->iplRate?->name ?? '-'),
                    $billing->isWater() ? (($billing->meter_start ?? '-').'-'.($billing->meter_end ?? '-').' ('.($billing->usage_m3 ?? '-').' m3)') : '-',
                    (string) $billing->total,
                    (string) $billing->paid_amount,
                    (string) $billing->remaining(),
                    $billing->statusLabel(),
                    $billing->due_date?->format('Y-m-d') ?? '-',
                ]);
            }

            fclose($handle);
        }, 'billings.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    protected function filteredQuery(): Builder
    {
        return Billing::query()
            ->when($this->search, fn (Builder $q) => $q->where(fn (Builder $qq) => $qq
                ->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhereHas('resident', fn (Builder $r) => $r->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('house', fn (Builder $h) => $h->where('house_number', 'like', "%{$this->search}%"))))
            ->when($this->statusFilter, fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn (Builder $q) => $q->where('billing_type', $this->typeFilter))
            ->when($this->blockFilter, fn (Builder $q) => $q->whereHas('house', fn (Builder $h) => $h->where('housing_block_id', $this->blockFilter)))
            ->when($this->periodMonth, fn (Builder $q) => $q->where('period_month', $this->periodMonth))
            ->when($this->periodYear, fn (Builder $q) => $q->where('period_year', $this->periodYear));
    }

    #[Layout('layouts.admin', ['title' => 'Tagihan IPL'])]
    public function render()
    {
        $query = $this->filteredQuery();

        $summary = [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('total'),
            'paid' => (float) (clone $query)->sum('paid_amount'),
            'unpaid' => (clone $query)->outstanding()->count(),
        ];

        return view('livewire.admin.ipl.billings.index', [
            'billings' => (clone $query)
                ->with(['house.block', 'resident', 'iplRate', 'waterRate'])
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->orderBy('house_id')
                ->paginate(15),
            'blocks' => HousingBlock::orderBy('code')->get(),
            'months' => Currency::MONTHS,
            'summary' => $summary,
            'cashAccounts' => CashAccount::active()->orderBy('name')->get(),
        ]);
    }
}
