<?php

namespace App\Livewire\Admin\ActivityLogs;

use App\Models\ActivityLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    #[Layout('layouts.admin', ['title' => 'Activity Log'])]
    public function render()
    {
        return view('livewire.admin.activity-logs.index', [
            'logs' => ActivityLog::with('user')
                ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%")->orWhere('module', 'like', "%{$this->search}%"))
                ->latest()->paginate(15),
        ]);
    }
}
