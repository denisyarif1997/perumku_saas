<?php

namespace App\Livewire\Admin\Water\Rates;

use App\Models\ActivityLog;
use App\Models\HousingEstate;
use App\Models\WaterRate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $housing_estate_id = '';

    public string $name = '';

    public string $price_per_m3 = '';

    public string $admin_fee = '0';

    public string $min_usage_m3 = '0';

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
        $rate = WaterRate::findOrFail($id);
        $this->editingId = $rate->id;
        $this->housing_estate_id = $rate->housing_estate_id ? (string) $rate->housing_estate_id : '';
        $this->name = $rate->name;
        $this->price_per_m3 = (string) (float) $rate->price_per_m3;
        $this->admin_fee = (string) (float) $rate->admin_fee;
        $this->min_usage_m3 = (string) (float) $rate->min_usage_m3;
        $this->effective_date = $rate->effective_date?->toDateString() ?? '';
        $this->end_date = $rate->end_date?->toDateString() ?? '';
        $this->description = $rate->description ?? '';
        $this->status = $rate->status;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'housing_estate_id', 'name', 'price_per_m3', 'end_date', 'description']);
        $this->admin_fee = '0';
        $this->min_usage_m3 = '0';
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
            'price_per_m3' => ['required', 'numeric', 'min:0'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'min_usage_m3' => ['nullable', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.required' => 'Nama tarif wajib diisi.',
            'price_per_m3.required' => 'Tarif per m3 wajib diisi.',
            'effective_date.required' => 'Tanggal berlaku wajib diisi.',
        ]);
        $data['housing_estate_id'] = $data['housing_estate_id'] ?: null;
        $data['admin_fee'] = $data['admin_fee'] === null || $data['admin_fee'] === '' ? 0 : $data['admin_fee'];
        $data['min_usage_m3'] = $data['min_usage_m3'] === null || $data['min_usage_m3'] === '' ? 0 : $data['min_usage_m3'];
        $data['end_date'] = $data['end_date'] ?: null;
        $data['description'] = $data['description'] ?: null;
        if ($this->editingId) {
            $rate = WaterRate::findOrFail($this->editingId);
            $old = $rate->toArray();
            $rate->update($data);
            ActivityLog::record(['user_id' => auth()->id(), 'action' => 'update', 'module' => 'water_rates',
                'subject_type' => WaterRate::class, 'subject_id' => $rate->id,
                'description' => 'Mengubah tarif air '.$rate->name, 'old_values' => $old, 'new_values' => $rate->fresh()->toArray()]);
            session()->flash('success', 'Tarif air berhasil diubah.');
        } else {
            $rate = WaterRate::create($data);
            ActivityLog::record(['user_id' => auth()->id(), 'action' => 'create', 'module' => 'water_rates',
                'subject_type' => WaterRate::class, 'subject_id' => $rate->id,
                'description' => 'Menambah tarif air '.$rate->name, 'new_values' => $rate->toArray()]);
            session()->flash('success', 'Tarif air berhasil ditambah.');
        }
        $this->cancel();
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);
        $rate = WaterRate::withCount(['readings', 'billings'])->findOrFail($id);
        if ($rate->readings_count > 0 || $rate->billings_count > 0) {
            session()->flash('error', 'Tarif tidak bisa dihapus karena sudah dipakai.');

            return;
        }
        $rate->delete();
        ActivityLog::record(['user_id' => auth()->id(), 'action' => 'delete', 'module' => 'water_rates',
            'subject_type' => WaterRate::class, 'subject_id' => $id,
            'description' => 'Menghapus tarif air '.$rate->name, 'old_values' => $rate->toArray()]);
        if ($this->editingId === $id) {
            $this->cancel();
        }
        session()->flash('success', 'Tarif air berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Tarif Air'])]
    public function render()
    {
        return view('livewire.admin.water.rates.index', [
            'rates' => WaterRate::with('estate')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderByDesc('effective_date')->paginate(10),
            'estates' => HousingEstate::orderBy('name')->get(),
        ]);
    }
}
