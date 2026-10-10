<?php

namespace App\Livewire\Admin\Inventory;

use App\Models\ActivityLog;
use App\Models\ItemLoan;
use App\Notifications\LoanStatusChanged;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Verifikasi dan proses peminjaman barang oleh warga.
 *
 * Setiap aksi memindahkan status pengajuan satu langkah:
 * diajukan → disetujui/ditolak → dipinjamkan → dikembalikan.
 */
class Loans extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** Catatan wajib saat barang diterima kembali. */
    public string $returnNote = '';

    public ?int $returningId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', ItemLoan::class);
    }

    /**
     * Setujui pengajuan supaya barang bisa diserahkan ke peminjam.
     */
    public function approve(int $id): void
    {
        $loan = ItemLoan::findOrFail($id);
        $this->authorize('review', $loan);

        $this->advance($loan, 'approved', [
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ], 'Pengajuan pinjam disetujui.');
    }

    /**
     * Tolak pengajuan. Barang tidak pernah keluar dari gudang.
     */
    public function reject(int $id): void
    {
        $loan = ItemLoan::findOrFail($id);
        $this->authorize('review', $loan);

        $this->advance($loan, 'rejected', [
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ], 'Pengajuan pinjam ditolak.');
    }

    /**
     * Serahkan barang ke peminjam. Mulai dihitung sebagai sedang dipinjam.
     */
    public function handOver(int $id): void
    {
        $loan = ItemLoan::with('item')->findOrFail($id);
        $this->authorize('handOver', $loan);

        // Stok dicek ulang saat diserahkan: barang bisa saja terpakai untuk
        // pengajuan lain sejak pengajuan ini disetujui.
        if (! $loan->item?->isLoanable()) {
            session()->flash('error', 'Barang ini sedang tidak bisa dipinjam. Periksa stok dan statusnya.');

            return;
        }

        $this->advance($loan, 'loaned', [
            'handed_over_by' => auth()->id(),
            'loaned_at' => now(),
        ], 'Barang diserahkan ke peminjam.');
    }

    /**
     * Buka formulir penerimaan barang kembali.
     */
    public function openReturn(int $id): void
    {
        $loan = ItemLoan::findOrFail($id);
        $this->authorize('receiveReturn', $loan);

        $this->returningId = $loan->id;
        $this->returnNote = '';
        $this->resetValidation();
    }

    public function closeReturn(): void
    {
        $this->returningId = null;
        $this->returnNote = '';
        $this->resetValidation();
    }

    /**
     * Terima kembali barang dari peminjam dan tutup proses peminjaman.
     */
    public function confirmReturn(int $id): void
    {
        $loan = ItemLoan::findOrFail($id);
        $this->authorize('receiveReturn', $loan);

        $note = trim($this->returnNote);

        $this->advance($loan, 'returned', [
            'returned_to' => auth()->id(),
            'returned_at' => now(),
            'return_note' => $note !== '' ? $note : null,
        ], 'Barang diterima kembali dari peminjam.');

        $this->closeReturn();
    }

    /**
     * Pindahkan status pengajuan satu langkah, catat jejak aktivitas, lalu
     * beri tahu warga peminjam.
     *
     * Transisi yang tidak sah dari status saat ini ditolak sebagai 403,
     * sehingga aksi tidak bisa dipanggil ulang untuk melewati langkah.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function advance(ItemLoan $loan, string $target, array $extra, string $message): void
    {
        abort_if(! $loan->canTransitionTo($target), 403, 'Status pengajuan tidak bisa diubah dari posisi sekarang.');

        DB::transaction(function () use ($loan, $target, $extra, $message): void {
            $loan->update($extra + ['status' => $target]);

            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'update',
                'module' => 'inventory',
                'subject_type' => ItemLoan::class,
                'subject_id' => $loan->id,
                'description' => $message.' — '.$loan->item?->name.' untuk '.$loan->borrowerName(),
                'old_values' => ['status' => $loan->getOriginal('status')],
                'new_values' => ['status' => $target],
            ]);

            // Pemohon diberi tahu setiap kali statusnya berubah.
            $loan->requester?->notify(new LoanStatusChanged($loan, $message));
        });

        session()->flash('success', $message);
        unset($this->loans);
    }

    #[Layout('layouts.admin', ['title' => 'Pinjam Barang'])]
    public function render()
    {
        $estateId = CurrentEstate::id();

        $loans = ItemLoan::query()
            ->with(['item', 'resident.houseResidents.house'])
            ->when($estateId !== null && $estateId !== CurrentEstate::NONE, function (Builder $q) use ($estateId) {
                $q->where('housing_estate_id', $estateId)
                    ->orWhereNull('housing_estate_id');
            })
            ->when($this->search, fn ($query) => $query->where(function (Builder $q): void {
                $q->whereHas('item', fn ($item) => $item->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('resident', fn ($r) => $r->where('name', 'like', "%{$this->search}%"));
            }))
            ->withStatus($this->statusFilter)
            ->orderByDesc('id')
            ->paginate(10);

        $summary = [
            'requested' => $this->estateIdFilter(ItemLoan::where('status', 'requested'), $estateId),
            'approved' => $this->estateIdFilter(ItemLoan::where('status', 'approved'), $estateId),
            'loaned' => $this->estateIdFilter(ItemLoan::where('status', 'loaned'), $estateId),
            'returned' => $this->estateIdFilter(ItemLoan::where('status', 'returned'), $estateId),
        ];

        return view('livewire.admin.inventory.loans', [
            'loans' => $loans,
            'statuses' => [
                '' => 'Semua',
                'requested' => 'Diajukan',
                'approved' => 'Disetujui',
                'loaned' => 'Dipinjamkan',
                'returned' => 'Dikembalikan',
                'rejected' => 'Ditolak',
                'cancelled' => 'Dibatalkan',
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Menghitung ringkasan status dengan filter estate. Mencegah loan dari
     * perumahan lain tampil di ringkasan admin walaupun data yang mewakili
     * rumah peminjamnya mengandung ID estate yang sama.
     */
    protected function estateIdFilter(Builder $query, ?int $estateId): int
    {
        if ($estateId === null || $estateId === CurrentEstate::NONE) {
            return (int) $query->count();
        }

        return (int) $query->where('housing_estate_id', $estateId)
            ->orWhereNull('housing_estate_id')
            ->count();
    }
}
