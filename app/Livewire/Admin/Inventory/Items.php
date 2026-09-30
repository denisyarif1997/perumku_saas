<?php

namespace App\Livewire\Admin\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\ItemLoan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kelola data master barang inventaris: menambah, mengubah, dan menghapus
 * barang yang bisa dipinjam warga.
 */
class Items extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $quantity = '1';

    public string $unit = 'unit';

    public string $location = '';

    public string $status = 'available';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function createItem(): void
    {
        $this->authorize('create', InventoryItem::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = InventoryItem::findOrFail($id);
        $this->authorize('update', $item);

        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->code = (string) $item->code;
        $this->description = (string) $item->description;
        $this->quantity = (string) $item->quantity;
        $this->unit = (string) $item->unit;
        $this->location = (string) $item->location;
        $this->status = $item->status;

        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Aturan validasi untuk field barang. Kode barang unik per perumahan.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('inventory_items', 'code')
                    ->where(fn ($query) => $query->whereNull('deleted_at'))
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:0', 'max:10000'],
            'unit' => ['required', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:available,maintenance,retired'],
        ];
    }

    /**
     * Simpan barang baru atau perubahan barang yang sudah ada.
     */
    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $payload = [
                'name' => $data['name'],
                'code' => $this->nullableText($data['code'] ?? null),
                'description' => $this->nullableText($data['description'] ?? null),
                'quantity' => (int) $data['quantity'],
                'unit' => $data['unit'],
                'location' => $this->nullableText($data['location'] ?? null),
                'status' => $data['status'],
            ];

            if ($this->editingId !== null) {
                $item = InventoryItem::findOrFail($this->editingId);
                $this->authorize('update', $item);

                $oldValues = $item->toArray();
                $item->update($payload);

                ActivityLog::record([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'module' => 'inventory',
                    'subject_type' => InventoryItem::class,
                    'subject_id' => $item->id,
                    'description' => 'Mengubah barang inventaris: '.$item->name,
                    'old_values' => $oldValues,
                    'new_values' => $item->toArray(),
                ]);

                session()->flash('success', 'Barang "'.$item->name.'" berhasil diperbarui.');
            } else {
                $item = InventoryItem::create($payload + ['created_by' => auth()->id()]);

                ActivityLog::record([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'module' => 'inventory',
                    'subject_type' => InventoryItem::class,
                    'subject_id' => $item->id,
                    'description' => 'Menambah barang inventaris: '.$item->name,
                    'new_values' => $item->toArray(),
                ]);

                session()->flash('success', 'Barang "'.$item->name.'" berhasil ditambahkan.');
            }
        });

        $this->closeForm();
        unset($this->items);
    }

    /**
     * Hapus barang. Barang yang sedang dipinjam tidak boleh dihapus supaya
     * riwayat peminjaman tidak jadi yatim.
     */
    public function delete(int $id): void
    {
        $item = InventoryItem::withCount(['loans as active_loans_count' => fn ($query) => $query->where('status', 'loaned')])
            ->findOrFail($id);
        $this->authorize('delete', $item);

        if ($item->active_loans_count > 0) {
            session()->flash('error', 'Barang "'.$item->name.'" masih dipinjam warga, tidak bisa dihapus.');

            return;
        }

        DB::transaction(function () use ($item): void {
            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'module' => 'inventory',
                'subject_type' => InventoryItem::class,
                'subject_id' => $item->id,
                'description' => 'Menghapus barang inventaris: '.$item->name,
                'old_values' => $item->toArray(),
            ]);

            $item->loans()->delete();
            $item->delete();
        });

        session()->flash('success', 'Barang "'.$item->name.'" berhasil dihapus.');
    }

    /**
     * Ubah string kosong menjadi null supaya kolom opsional tetap bersih.
     */
    protected function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'quantity', 'unit', 'location', 'status']);

        $this->quantity = '1';
        $this->unit = 'unit';
        $this->status = 'available';

        $this->resetValidation();
    }

    #[Layout('layouts.admin', ['title' => 'Inventaris Barang'])]
    public function render()
    {
        return view('livewire.admin.inventory.items', [
            'items' => InventoryItem::query()
                ->withCount(['loans as borrowed_count' => fn ($query) => $query->where('status', 'loaned')])
                ->search($this->search)
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->orderBy('name')
                ->paginate(10),
            'statuses' => InventoryItem::statuses(),
            'summary' => [
                'total' => InventoryItem::count(),
                'units' => (int) InventoryItem::sum('quantity'),
                'borrowed' => ItemLoan::where('status', 'loaned')->count(),
            ],
        ]);
    }
}
