<?php

namespace App\Livewire\Resident\Ipl;

use App\Models\Billing;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $yearFilter = '';

    public function mount(): void
    {
        $this->requireResident();
        $this->yearFilter = '';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'typeFilter', 'yearFilter'], true)) {
            $this->resetPage();
        }
    }

    protected function requireResident(): void
    {
        abort_unless(auth()->user()->resident_id, 403, 'Akun ini tidak terhubung dengan data warga.');
    }

    /**
     * Tagihan milik warga: berdasarkan relasi langsung atau hunian aktif.
     */
    protected function residentQuery(): Builder
    {
        $residentId = (int) auth()->user()->resident_id;

        return Billing::query()
            ->where(fn (Builder $q) => $q
                ->where('resident_id', $residentId)
                ->orWhereHas('house.houseResidents', fn (Builder $h) => $h
                    ->where('resident_id', $residentId)
                    ->where('status', 'active')));
    }

    #[Layout('layouts.resident', ['title' => 'Tagihan IPL'])]
    public function render()
    {
        $query = $this->residentQuery();

        $outstandingQuery = (clone $query)->outstanding();

        $summary = [
            'outstandingCount' => (clone $outstandingQuery)->count(),
            'outstandingAmount' => (float) (clone $outstandingQuery)->sum('total')
                - (float) (clone $outstandingQuery)->sum('paid_amount'),
            'paidThisYear' => (float) (clone $query)
                ->where('period_year', now()->year)
                ->where('status', 'paid')
                ->sum('paid_amount'),
        ];

        return view('livewire.resident.ipl.index', [
            'billings' => (clone $query)
                ->with(['house.block', 'iplRate', 'waterRate'])
                ->when($this->statusFilter, fn (Builder $q) => $q->where('status', $this->statusFilter))
                ->when($this->typeFilter, fn (Builder $q) => $q->where('billing_type', $this->typeFilter))
                ->when($this->yearFilter, fn (Builder $q) => $q->where('period_year', $this->yearFilter))
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->paginate(12),
            'years' => (clone $query)->select('period_year')->distinct()->orderByDesc('period_year')->pluck('period_year'),
            'summary' => $summary,
        ]);
    }
}
