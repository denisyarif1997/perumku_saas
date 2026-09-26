<?php

namespace App\Livewire\Admin\Houses;

use App\Models\ActivityLog;
use App\Models\House;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $blockFilter = '';

    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBlockFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->authorize('delete', House::class);
        $house = House::withCount(['houseResidents', 'outstandingBillings'])->findOrFail($id);

        if ($house->house_residents_count > 0) {
            session()->flash('error', 'Rumah '.$house->fullLabel().' tidak bisa dihapus karena masih ada '.$house->house_residents_count.' data warga.');

            return;
        }

        if ($house->outstanding_billings_count > 0) {
            session()->flash('error', 'Rumah '.$house->fullLabel().' tidak bisa dihapus karena masih ada '.$house->outstanding_billings_count.' tagihan belum lunas.');

            return;
        }

        $old = $house->toArray();
        $house->delete();

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'module' => 'houses',
            'subject_type' => House::class,
            'subject_id' => $id,
            'description' => 'Menghapus rumah '.($house->block?->code.'-'.$house->house_number),
            'old_values' => $old,
        ]);

        session()->flash('success', 'Rumah berhasil dihapus.');
    }

    public function export()
    {
        $this->authorize('viewAny', House::class);

        $houses = House::with(['block', 'estate', 'houseResidents.resident'])
            ->when($this->search, fn ($q) => $q->where('house_number', 'like', "%{$this->search}%")
                ->orWhere('address', 'like', "%{$this->search}%"))
            ->when($this->blockFilter, fn ($q) => $q->where('housing_block_id', $this->blockFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($houses) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Rumah', 'Alamat', 'Blok', 'Estate', 'Penghuni', 'Status']);

            foreach ($houses as $house) {
                fputcsv($handle, [
                    $house->fullLabel(),
                    $house->address ?? '',
                    $house->block?->code ?? '',
                    $house->estate?->name ?? '',
                    $house->houseResidents->first()?->resident?->name ?? '',
                    $house->status ?? '',
                ]);
            }

            fclose($handle);
        }, 'houses.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    #[Layout('layouts.admin', ['title' => 'Rumah'])]
    public function render()
    {
        $houses = House::with(['block', 'estate', 'houseResidents.resident'])
            ->withCount(['houseResidents', 'outstandingBillings'])
            ->when($this->search, fn ($q) => $q->where('house_number', 'like', "%{$this->search}%")->orWhere('address', 'like', "%{$this->search}%"))
            ->when($this->blockFilter, fn ($q) => $q->where('housing_block_id', $this->blockFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        return view('livewire.admin.houses.index', [
            'houses' => $houses,
            'blocks' => HousingBlock::orderBy('code')->get(),
            'estates' => HousingEstate::orderBy('name')->get(),
        ]);
    }
}
