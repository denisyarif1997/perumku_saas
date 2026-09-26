<?php

namespace App\Livewire\Resident\Info;

use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $categoryFilter = '';

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.resident', ['title' => 'Info & Pengumuman'])]
    public function render()
    {
        return view('livewire.resident.info.index', [
            'announcements' => Announcement::published()
                ->with('author')
                ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }
}
