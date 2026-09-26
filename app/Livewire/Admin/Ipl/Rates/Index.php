<?php

namespace App\Livewire\Admin\Ipl\Rates;

use App\Models\ActivityLog;
use App\Models\HousingEstate;
use App\Models\IplRate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $housing_estate_id = '';

    public string $name = '';

    public string $amount = '';

    public string $period_type = 'monthly';

    public string $effective_date = '';

    public string $end_date = '';

    public string $description = '';

    public string $status = 'active';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $this->effective_date = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $rate = IplRate::findOrFail($id);
        $this->editingId = $rate->id;
        $this->housing_estate_id = $rate->housing_estate_id ? (string) $rate->housing_estate_id : '';
        $this->name = $rate->name;
        $this->amount = (string) (float) $rate->amount;
        $this->period_type = $rate->period_type;
        $this->effective_date = $rate->effective_date?->toDateString() ?? '';
        $this->end_date = $rate->end_date?->toDateString() ?? '';
        $this->description = $rate->description ?? '';
        $this->status = $rate->status;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'housing_estate_id', 'name', 'amount', 'end_date', 'description']);
        $this->period_type = 'monthly';
        $this->status = 'active';
        $this->effective_date = now()->toDateString();
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $data = $this->validate([
            'housing_estate_id' => ['nullable', 'exists:housing_estates,id'],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'period_type' => ['required', 'in:monthly,quarterly,yearly'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.required' => 'Nama tarif wajib diisi.',
            'amount.required' => 'Nominal IPL wajib diisi.',
            'amount.min' => 'Nominal IPL tidak boleh negatif.',
            'effective_date.required' => 'Tanggal berlaku wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal berlaku.',
        ]);

        $data['housing_estate_id'] = $data['housing_estate_id'] ?: null;
        $data['end_date'] = $data['end_date'] ?: null;
        $data['description'] = $data['description'] ?: null;

        if ($this->editingId) {
            $rate = IplRate::findOrFail($this->editingId);
            $old = $rate->toArray();
            $rate->update($data);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'ipl_rates',
                'subject_type' => IplRate::class, 'subject_id' => $rate->id,
                'description' => 'Mengubah tarif IPL '.$rate->name,
                'old_values' => $old, 'new_values' => $rate->fresh()->toArray(),
            ]);

            session()->flash('success', 'Tarif IPL berhasil diubah.');
        } else {
            $rate = IplRate::create($data);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'create', 'module' => 'ipl_rates',
                'subject_type' => IplRate::class, 'subject_id' => $rate->id,
                'description' => 'Menambah tarif IPL '.$rate->name,
                'new_values' => $rate->toArray(),
            ]);

            session()->flash('success', 'Tarif IPL berhasil ditambah.');
        }

        $this->cancel();
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $rate = IplRate::withCount('billings')->findOrFail($id);

        if ($rate->billings_count > 0) {
            session()->flash('error', 'Tarif tidak bisa dihapus karena sudah dipakai pada tagihan.');

            return;
        }

        $rate->delete();

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'delete', 'module' => 'ipl_rates',
            'subject_type' => IplRate::class, 'subject_id' => $id,
            'description' => 'Menghapus tarif IPL '.$rate->name,
            'old_values' => $rate->toArray(),
        ]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('success', 'Tarif IPL berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Tarif IPL'])]
    public function render()
    {
        return view('livewire.admin.ipl.rates.index', [
            'rates' => IplRate::with('estate')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderByDesc('effective_date')
                ->paginate(10),
            'estates' => HousingEstate::orderBy('name')->get(),
        ]);
    }
}
