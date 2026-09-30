<?php

namespace App\Livewire\Resident\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\ItemLoan;
use App\Models\User;
use App\Notifications\NewItemLoan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sisi warga: daftar barang yang bisa dipinjam, form pengajuan, dan
 * riwayat peminjaman milik warga tersebut.
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public string $itemId = '';

    public string $purpose = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    /**
     * Buka form pengajuan untuk satu barang.
     */
    public function requestLoan(int $itemId): void
    {
        $this->authorize('create', ItemLoan::class);

        $item = InventoryItem::findOrFail($itemId);

        // Barang harus benar-benar bisa dipinjam; daftar bisa saja sudah
        // berubah sejak warga membuka halaman.
        abort_unless($item->isLoanable(), 422, 'Barang ini sedang tidak bisa dipinjam.');

        $this->itemId = (string) $item->id;
        $this->purpose = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->itemId = '';
        $this->purpose = '';
        $this->resetValidation();
    }

    /**
     * Warga mengirim pengajuan pinjam. Pengajuan masuk antrean pengelola
     * dengan status "diajukan".
     */
    public function submit(): void
    {
        $user = auth()->user();
        $this->authorize('create', ItemLoan::class);

        $data = $this->validate([
            'itemId' => ['required', 'integer', 'exists:inventory_items,id'],
            'purpose' => ['required', 'string', 'max:255'],
        ], [
            'itemId.required' => 'Pilih barang yang ingin dipinjam.',
            'itemId.exists' => 'Barang tidak ditemukan.',
            'purpose.required' => 'Tulis alasan peminjaman.',
        ]);

        $item = InventoryItem::findOrFail((int) $data['itemId']);
        abort_unless($item->isLoanable(), 422, 'Barang ini sedang tidak bisa dipinjam.');

        $loan = DB::transaction(function () use ($item, $data, $user): ItemLoan {
            $loan = ItemLoan::create([
                'inventory_item_id' => $item->id,
                'resident_id' => $user->resident_id,
                'user_id' => $user->id,
                'purpose' => $data['purpose'],
                'status' => 'requested',
            ]);

            ActivityLog::record([
                'user_id' => $user->id,
                'action' => 'create',
                'module' => 'inventory',
                'subject_type' => ItemLoan::class,
                'subject_id' => $loan->id,
                'description' => 'Warga mengajukan pinjam: '.$item->name,
                'new_values' => $loan->toArray(),
            ]);

            return $loan;
        });

        // Beri tahu pengelola inventaris bahwa ada pengajuan baru.
        User::staffWithPermission('manage-inventory')
            ->where('id', '!=', $user->id)
            ->get()
            ->each(fn (User $staff) => $staff->notify(new NewItemLoan($loan, $user->name)));

        $this->closeForm();
        session()->flash('success', 'Pengajuan pinjam "'.$item->name.'" berhasil dikirim dan menunggu persetujuan.');
    }

    /**
     * Warga membatalkan pengajuannya sendiri selama belum diproses.
     */
    public function cancel(int $id): void
    {
        $loan = ItemLoan::findOrFail($id);
        $this->authorize('cancel', $loan);

        abort_if($loan->status !== 'requested', 403, 'Pengajuan sudah diproses dan tidak bisa dibatalkan.');

        DB::transaction(function () use ($loan): void {
            $loan->update(['status' => 'cancelled']);

            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'update',
                'module' => 'inventory',
                'subject_type' => ItemLoan::class,
                'subject_id' => $loan->id,
                'description' => 'Warga membatalkan pengajuan pinjam: '.$loan->item?->name,
                'old_values' => ['status' => 'requested'],
                'new_values' => ['status' => 'cancelled'],
            ]);
        });

        session()->flash('success', 'Pengajuan pinjam dibatalkan.');
    }

    #[Layout('layouts.resident', ['title' => 'Pinjam Barang'])]
    public function render()
    {
        $residentId = (int) auth()->user()->resident_id;

        return view('livewire.resident.inventory.index', [
            'items' => InventoryItem::query()
                ->withCount(['loans as borrowed_count' => fn ($query) => $query->where('status', 'loaned')])
                ->available()
                ->search($this->search)
                ->orderBy('name')
                ->paginate(8),
            'loans' => ItemLoan::query()
                ->with('item')
                ->forResident($residentId)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'summary' => [
                'requested' => ItemLoan::forResident($residentId)->where('status', 'requested')->count(),
                'loaned' => ItemLoan::forResident($residentId)->where('status', 'loaned')->count(),
                'returned' => ItemLoan::forResident($residentId)->where('status', 'returned')->count(),
            ],
        ]);
    }
}
