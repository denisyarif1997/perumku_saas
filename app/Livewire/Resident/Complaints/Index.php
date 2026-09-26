<?php

namespace App\Livewire\Resident\Complaints;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\NewComplaint;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public bool $showForm = false;

    public string $title = '';

    public string $description = '';

    public string $category = 'general';

    public string $priority = 'normal';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openForm(): void
    {
        $this->showForm = true;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->reset(['title', 'description', 'category', 'priority']);
        $this->resetValidation();
    }

    /**
     * Warga membuat pengaduan baru (terhubung dengan rumah huniannya).
     */
    public function submit(): void
    {
        $user = auth()->user();
        abort_if(! $user->resident_id, 403, 'Akun tidak terhubung dengan data warga.');

        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'in:general,water,electricity,security,cleanliness,facility,neighbor'],
            'priority' => ['required', 'in:low,normal,high'],
        ], [
            'title.required' => 'Judul pengaduan wajib diisi.',
            'description.required' => 'Deskripsi pengaduan wajib diisi.',
        ]);

        $residentId = (int) $user->resident_id;
        $houseId = DB::table('house_residents')
            ->where('resident_id', $residentId)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->value('house_id');

        $complaint = DB::transaction(function () use ($data, $residentId, $houseId, $user) {
            $complaint = Complaint::create([
                'resident_id' => $residentId,
                'house_id' => $houseId,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'open',
            ]);

            // ticket_number terisi otomatis oleh hook created pada model.

            ActivityLog::record([
                'user_id' => $user->id,
                'action' => 'create',
                'module' => 'complaints',
                'subject_type' => Complaint::class,
                'subject_id' => $complaint->id,
                'description' => 'Pengaduan baru: '.$complaint->ticket_number.' — '.$complaint->title,
                'new_values' => $complaint->toArray(),
            ]);

            return $complaint;
        });

        // Beri tahu staf penanggung jawab laporan bahwa ada pengaduan baru.
        User::staffWithPermission('manage-complaint')
            ->where('id', '!=', $user->id)
            ->get()
            ->each(fn (User $staff) => $staff->notify(new NewComplaint($complaint, $user->name)));

        $this->closeForm();
        session()->flash('success', 'Pengaduan berhasil dikirim dengan nomor tiket '.$complaint->ticket_number.'.');
        $this->redirectRoute('resident.complaints.show', $complaint, navigate: true);
    }

    #[Layout('layouts.resident', ['title' => 'Pengaduan Saya'])]
    public function render()
    {
        $residentId = (int) auth()->user()->resident_id;

        return view('livewire.resident.complaints.index', [
            'complaints' => Complaint::query()
                ->where('resident_id', $residentId)
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->orderByDesc('id')
                ->paginate(10),
            'summary' => [
                'open' => Complaint::where('resident_id', $residentId)->open()->count(),
                'progress' => Complaint::where('resident_id', $residentId)->inProgress()->count(),
                'resolved' => Complaint::where('resident_id', $residentId)->closed()->count(),
            ],
        ]);
    }
}
