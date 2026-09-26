<?php

namespace App\Livewire\Admin\Estates;

use App\Models\ActivityLog;
use App\Models\HousingEstate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $code = '';

    public string $name = '';

    public ?int $editingId = null;

    public string $editCode = '';

    public string $editName = '';

    public string $editAddress = '';

    public string $editPhone = '';

    public string $editEmail = '';

    public function create(): void
    {
        $this->cancelEdit();
        $this->resetErrorBag();
        $this->reset(['code', 'name']);
    }

    public function save(): void
    {
        $this->authorize('create', HousingEstate::class);

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', 'unique:housing_estates,code'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $estate = HousingEstate::create($data + ['status' => 'active']);
        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'create',
            'module' => 'housing_estates',
            'subject_type' => HousingEstate::class,
            'subject_id' => $estate->id,
            'description' => 'Menambah perumahan '.$estate->name,
        ]);

        $this->reset(['code', 'name']);
        session()->flash('success', 'Perumahan berhasil ditambah.');
    }

    public function edit(HousingEstate $estate): void
    {
        $this->editingId = $estate->id;
        $this->editCode = $estate->code;
        $this->editName = $estate->name;
        $this->editAddress = $estate->address ?? '';
        $this->editPhone = $estate->phone ?? '';
        $this->editEmail = $estate->email ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['editCode', 'editName', 'editAddress', 'editPhone', 'editEmail']);
    }

    public function update(): void
    {
        $this->authorize('update', HousingEstate::class);

        $estate = HousingEstate::findOrFail($this->editingId);

        $data = $this->validate([
            'editCode' => ['required', 'string', 'max:20', 'unique:housing_estates,code,'.$this->editingId],
            'editName' => ['required', 'string', 'max:100'],
            'editAddress' => ['nullable', 'string', 'max:255'],
            'editPhone' => ['nullable', 'string', 'max:20'],
            'editEmail' => ['nullable', 'email', 'max:100'],
        ]);

        $estate->update([
            'code' => $data['editCode'],
            'name' => $data['editName'],
            'address' => $data['editAddress'],
            'phone' => $data['editPhone'],
            'email' => $data['editEmail'],
        ]);

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'housing_estates',
            'subject_type' => HousingEstate::class,
            'subject_id' => $estate->id,
            'description' => 'Mengubah perumahan '.$estate->name,
        ]);

        session()->flash('success', 'Perumahan berhasil diperbarui.');
        $this->cancelEdit();
    }

    public function delete(HousingEstate $estate): void
    {
        $this->authorize('delete', HousingEstate::class);

        $estate->loadCount(['blocks', 'houses']);

        if ($estate->houses_count > 0) {
            session()->flash('error', 'Perumahan '.$estate->name.' tidak bisa dihapus karena masih ada '.$estate->houses_count.' rumah.');

            return;
        }

        if ($estate->blocks_count > 0) {
            session()->flash('error', 'Perumahan '.$estate->name.' tidak bisa dihapus karena masih ada '.$estate->blocks_count.' blok.');

            return;
        }

        $name = $estate->name;
        $estate->delete();

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'module' => 'housing_estates',
            'subject_type' => HousingEstate::class,
            'subject_id' => $estate->id,
            'description' => 'Menghapus perumahan '.$name,
        ]);

        session()->flash('success', 'Perumahan berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Perumahan'])]
    public function render()
    {
        return view('livewire.admin.estates.index', [
            'estates' => HousingEstate::withCount(['blocks', 'houses'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(10),
        ]);
    }
}
