<?php

namespace App\Livewire\Admin\Cash\Accounts;

use App\Models\ActivityLog;
use App\Models\CashAccount;
use App\Models\HousingEstate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $housing_estate_id = '';

    public string $name = '';

    public string $type = 'cash';

    public string $account_number = '';

    public string $account_holder = '';

    public string $opening_balance = '0';

    public string $description = '';

    public string $status = 'active';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);
        $account = CashAccount::findOrFail($id);
        $this->editingId = $account->id;
        $this->housing_estate_id = $account->housing_estate_id ? (string) $account->housing_estate_id : '';
        $this->name = $account->name;
        $this->type = $account->type;
        $this->account_number = $account->account_number ?? '';
        $this->account_holder = $account->account_holder ?? '';
        $this->opening_balance = (string) (float) $account->opening_balance;
        $this->description = $account->description ?? '';
        $this->status = $account->status;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'housing_estate_id', 'name', 'type', 'account_number', 'account_holder', 'description']);
        $this->opening_balance = '0';
        $this->type = 'cash';
        $this->status = 'active';
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);
        $data = $this->validate([
            'housing_estate_id' => ['nullable', 'exists:housing_estates,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:cash,bank'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'account_holder' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.required' => 'Nama kas wajib diisi.',
            'opening_balance.required' => 'Saldo awal wajib diisi.',
        ]);
        $data['housing_estate_id'] = $data['housing_estate_id'] ?: null;
        $data['account_number'] = $data['account_number'] ?: null;
        $data['account_holder'] = $data['account_holder'] ?: null;
        $data['description'] = $data['description'] ?: null;

        if ($this->editingId) {
            $account = CashAccount::findOrFail($this->editingId);
            $old = $account->toArray();
            $account->update($data);
            ActivityLog::record(['user_id' => auth()->id(), 'action' => 'update', 'module' => 'cash_accounts',
                'subject_type' => CashAccount::class, 'subject_id' => $account->id,
                'description' => 'Mengubah kas '.$account->name, 'old_values' => $old, 'new_values' => $account->fresh()->toArray()]);
            session()->flash('success', 'Kas berhasil diubah.');
        } else {
            $account = CashAccount::create($data);
            ActivityLog::record(['user_id' => auth()->id(), 'action' => 'create', 'module' => 'cash_accounts',
                'subject_type' => CashAccount::class, 'subject_id' => $account->id,
                'description' => 'Menambah kas '.$account->name, 'new_values' => $account->toArray()]);
            session()->flash('success', 'Kas berhasil ditambah.');
        }
        $this->cancel();
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-finance'), 403);
        $account = CashAccount::withCount(['transactions', 'incomingTransfers'])->findOrFail($id);
        if ($account->transactions_count > 0 || $account->incoming_transfers_count > 0) {
            session()->flash('error', 'Kas tidak bisa dihapus karena sudah memiliki transaksi.');

            return;
        }
        $account->delete();
        ActivityLog::record(['user_id' => auth()->id(), 'action' => 'delete', 'module' => 'cash_accounts',
            'subject_type' => CashAccount::class, 'subject_id' => $id,
            'description' => 'Menghapus kas '.$account->name, 'old_values' => $account->toArray()]);
        if ($this->editingId === $id) {
            $this->cancel();
        }
        session()->flash('success', 'Kas berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Kas Warga'])]
    public function render()
    {
        return view('livewire.admin.cash.accounts.index', [
            'accounts' => CashAccount::with('estate')
                ->withCount(['transactions', 'incomingTransfers'])
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('account_number', 'like', "%{$this->search}%")))
                ->orderBy('name')
                ->paginate(10),
            'estates' => HousingEstate::orderBy('name')->get(),
        ]);
    }
}
