<?php

namespace App\Livewire\Admin\Blocks;

use App\Models\ActivityLog;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $housing_estate_id = '';

    public string $code = '';

    public string $name = '';

    public function mount(): void
    {
        // Admin estate terikat ke satu perumahan, sehingga field-nya tidak
        // dirender sebagai dropdown (lihat x-ui.estate-field). Isi otomatis
        // agar aturan validasi "required" tetap terpenuhi.
        $estates = HousingEstate::orderBy('name')->get();

        if ($estates->count() === 1) {
            $this->housing_estate_id = (string) $estates->first()->id;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'housing_estate_id' => ['required', 'exists:housing_estates,id'],
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $exists = HousingBlock::where('housing_estate_id', $data['housing_estate_id'])->where('code', $data['code'])->exists();
        if ($exists) {
            $this->addError('code', 'Kode blok sudah ada di perumahan ini.');

            return;
        }

        $block = HousingBlock::create($data + ['status' => 'active']);
        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'create', 'module' => 'housing_blocks',
            'subject_type' => HousingBlock::class, 'subject_id' => $block->id,
            'description' => 'Menambah blok '.$block->code,
        ]);

        $this->reset(['code', 'name']);
        session()->flash('success', 'Blok berhasil ditambah.');
    }

    public function delete(int $id): void
    {
        $block = HousingBlock::withCount('houses')->findOrFail($id);
        if ($block->houses_count > 0) {
            session()->flash('error', 'Blok tidak bisa dihapus karena masih ada rumah.');

            return;
        }
        $block->delete();
        session()->flash('success', 'Blok berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Blok'])]
    public function render()
    {
        return view('livewire.admin.blocks.index', [
            'blocks' => HousingBlock::with(['estate'])->withCount('houses')
                ->when($this->search, fn ($q) => $q->where('code', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))
                ->latest()->paginate(10),
            'estates' => HousingEstate::orderBy('name')->get(),
        ]);
        
    }
    
}
