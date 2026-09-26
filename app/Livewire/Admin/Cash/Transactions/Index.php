<?php

namespace App\Livewire\Admin\Cash\Transactions;

use App\Models\ActivityLog;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $type = 'in'; // in | out | transfer

    public string $cash_account_id = '';

    public string $destination_account_id = '';

    public string $amount = '';

    public string $transaction_date = '';

    public string $category = '';

    public string $reference = '';

    public string $description = '';

    public string $search = '';

    public string $filterType = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);
        $this->transaction_date = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->reset(['destination_account_id', 'category', 'reference', 'description']);
        $this->resetValidation();
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, [CashTransaction::TYPE_IN, CashTransaction::TYPE_OUT, CashTransaction::TYPE_TRANSFER], true)
            ? $type
            : CashTransaction::TYPE_IN;
        $this->updatedType($type);
    }

    public function resetForm(): void
    {
        $this->reset(['cash_account_id', 'destination_account_id', 'amount', 'category', 'reference', 'description']);
        $this->type = CashTransaction::TYPE_IN;
        $this->transaction_date = now()->toDateString();
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);

        $rules = [
            'type' => ['required', 'in:in,out,transfer'],
            'cash_account_id' => ['required', 'exists:cash_accounts,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_date' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->type === CashTransaction::TYPE_TRANSFER) {
            $rules['destination_account_id'] = ['required', 'exists:cash_accounts,id', 'different:cash_account_id'];
        }

        $messages = [
            'cash_account_id.required' => 'Kas sumber wajib dipilih.',
            'destination_account_id.required' => 'Kas tujuan wajib dipilih.',
            'destination_account_id.different' => 'Kas tujuan tidak boleh sama dengan kas sumber.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.min' => 'Nominal minimal Rp 1.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
        ];

        $data = $this->validate($rules, $messages);
        $source = CashAccount::findOrFail($data['cash_account_id']);

        // Cek saldo cukup untuk kas keluar / transfer.
        if (in_array($this->type, [CashTransaction::TYPE_OUT, CashTransaction::TYPE_TRANSFER], true)
            && (float) $source->currentBalance() < (float) $data['amount']) {
            $this->addError('amount', 'Saldo kas '.$source->name.' tidak cukup. Saldo tersedia: Rp '.number_format((float) $source->currentBalance(), 0, ',', '.'));

            return;
        }

        $label = match ($this->type) {
            CashTransaction::TYPE_IN => 'Kas masuk',
            CashTransaction::TYPE_OUT => 'Kas keluar',
            CashTransaction::TYPE_TRANSFER => 'Transfer antar kas',
        };

        DB::transaction(function () use ($data, $source, $label) {
            $created = CashTransaction::create($data + ['created_by' => auth()->id()]);

            ActivityLog::record(['user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_transactions',
                'subject_type' => CashTransaction::class, 'subject_id' => $created->id,
                'description' => $label.' '.$source->name.
                    ($this->type === CashTransaction::TYPE_TRANSFER
                        ? ' ke '.CashAccount::find($data['destination_account_id'])?->name
                        : '').
                    ' sebesar Rp '.number_format((float) $created->amount, 0, ',', '.'),
                'new_values' => $created->toArray()]);
        });

        session()->flash('success', $label.' berhasil dicatat.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);
        $transaction = CashTransaction::findOrFail($id);

        // Transaksi dari pembayaran IPL hanya boleh dibalikkan lewat modul pembayaran/tagihan.
        if ($transaction->payment_id) {
            session()->flash('error', 'Transaksi ini terhubung ke pembayaran IPL. Pembalikannya otomatis tercatat saat tagihan dibatalkan atau pembayaran dihapus/ditolak.');

            return;
        }

        $old = $transaction->toArray();
        $transaction->delete();
        ActivityLog::record(['user_id' => auth()->id(), 'action' => 'delete', 'module' => 'cash_transactions',
            'subject_type' => CashTransaction::class, 'subject_id' => $id,
            'description' => 'Menghapus transaksi kas #'.$id, 'old_values' => $old]);
        session()->flash('success', 'Transaksi berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Transaksi Kas'])]
    public function render()
    {
        $activeAccounts = CashAccount::active()->orderBy('name')->get();

        return view('livewire.admin.cash.transactions.index', [
            'transactions' => CashTransaction::with(['account', 'destinationAccount', 'creator'])
                ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                    ->where('description', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhere('category', 'like', "%{$this->search}%")))
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->paginate(10),
            'accounts' => $activeAccounts,
            'totalIn' => CashTransaction::where('type', CashTransaction::TYPE_IN)->sum('amount'),
            'totalOut' => CashTransaction::where('type', CashTransaction::TYPE_OUT)->sum('amount'),
            'totalTransfer' => CashTransaction::where('type', CashTransaction::TYPE_TRANSFER)->sum('amount'),
        ]);
    }
}
