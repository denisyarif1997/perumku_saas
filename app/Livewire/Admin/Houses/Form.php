<?php

namespace App\Livewire\Admin\Houses;

use App\Models\ActivityLog;
use App\Models\House;
use App\Models\HousingBlock;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    public ?House $house = null;

    public string $housing_block_id = '';

    public string $house_number = '';

    public string $address = '';

    public ?float $land_area = null;

    public ?float $building_area = null;

    public string $ownership_status = 'owner';

    public string $occupancy_status = 'occupied';

    public string $status = 'active';

    public function mount(?House $house = null): void
    {
        $this->authorize($house?->exists ? 'update' : 'create', $house?->exists ? $house : House::class);
        if ($house?->exists) {
            $this->house = $house;
            $this->housing_block_id = (string) $house->housing_block_id;
            $this->house_number = $house->house_number;
            $this->address = $house->address ?? '';
            $this->land_area = $house->land_area ? (float) $house->land_area : null;
            $this->building_area = $house->building_area ? (float) $house->building_area : null;
            $this->ownership_status = $house->ownership_status;
            $this->occupancy_status = $house->occupancy_status;
            $this->status = $house->status;
        }
    }

    public function save()
    {
        $data = $this->validate([
            'housing_block_id' => ['required', 'exists:housing_blocks,id'],
            'house_number' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'land_area' => ['nullable', 'numeric', 'min:0'],
            'building_area' => ['nullable', 'numeric', 'min:0'],
            'ownership_status' => ['required', 'in:owner,rent,developer,other'],
            'occupancy_status' => ['required', 'in:occupied,empty,renovation'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'housing_block_id.required' => 'Blok wajib dipilih.',
            'house_number.required' => 'Nomor rumah wajib diisi.',
        ]);

        $block = HousingBlock::findOrFail($data['housing_block_id']);
        $exists = House::where('housing_block_id', $block->id)
            ->where('house_number', $data['house_number'])
            ->when($this->house, fn ($q) => $q->where('id', '!=', $this->house->id))
            ->exists();

        if ($exists) {
            $this->addError('house_number', 'Nomor rumah sudah ada di blok ini.');

            return;
        }

        $data['housing_estate_id'] = $block->housing_estate_id;

        if ($this->house) {
            $old = $this->house->toArray();
            $this->house->update($data);
            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'houses',
                'subject_type' => House::class, 'subject_id' => $this->house->id,
                'description' => 'Mengubah rumah '.$this->house->fullLabel(),
                'old_values' => $old, 'new_values' => $this->house->fresh()->toArray(),
            ]);
            session()->flash('success', 'Rumah berhasil diubah.');
        } else {
            $house = House::create($data);
            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'create', 'module' => 'houses',
                'subject_type' => House::class, 'subject_id' => $house->id,
                'description' => 'Menambah rumah '.$house->fullLabel(),
                'new_values' => $house->toArray(),
            ]);
            session()->flash('success', 'Rumah berhasil ditambah.');
        }

        return $this->redirectRoute('admin.houses.index', navigate: true);
    }

    #[Layout('layouts.admin', ['title' => 'Form Rumah'])]
    public function render()
    {
        return view('livewire.admin.houses.form', [
            'blocks' => HousingBlock::with('estate')->orderBy('code')->get(),
        ]);
    }
}
