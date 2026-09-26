<?php

namespace App\Livewire\Admin\Info;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Complaints extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public string $priorityFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-complaint'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'categoryFilter', 'priorityFilter'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'statusFilter', 'categoryFilter', 'priorityFilter']);
        $this->resetPage();
    }

    #[Layout('layouts.admin', ['title' => 'Laporan Warga'])]
    public function render()
    {
        $query = Complaint::query()
            ->when($this->search, fn (Builder $q) => $q->where(fn (Builder $qq) => $qq
                ->where('ticket_number', 'like', "%{$this->search}%")
                ->orWhere('title', 'like', "%{$this->search}%")
                ->orWhereHas('resident', fn (Builder $r) => $r->where('name', 'like', "%{$this->search}%"))))
            ->when($this->statusFilter, fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn (Builder $q) => $q->where('category', $this->categoryFilter))
            ->when($this->priorityFilter, fn (Builder $q) => $q->where('priority', $this->priorityFilter));

        $summary = [
            'open' => (clone $query)->open()->count(),
            'inProgress' => (clone $query)->inProgress()->count(),
            'closed' => (clone $query)->closed()->count(),
        ];

        return view('livewire.admin.info.complaints', [
            'complaints' => (clone $query)
                ->with(['resident', 'house', 'assignee'])
                ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")
                ->orderByRaw("case priority when 'high' then 0 when 'normal' then 1 else 2 end")
                ->orderByDesc('id')
                ->paginate(15),
            'summary' => $summary,
        ]);
    }
}
