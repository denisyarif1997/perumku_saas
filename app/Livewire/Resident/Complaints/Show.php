<?php

namespace App\Livewire\Resident\Complaints;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\ComplaintResponse;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public Complaint $complaint;

    public string $message = '';

    public function mount(Complaint $complaint): void
    {
        $this->authorize('view', $complaint);
        $this->complaint = $complaint;
    }

    /**
     * Warga menambahkan tanggapan/tindak lanjut pada pengaduannya sendiri.
     * Menandai selesai juga boleh dilakukan warga dari sisi pengaduannya.
     */
    public function addResponse(): void
    {
        $this->authorize('view', $this->complaint);

        $data = $this->validate([
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'message.required' => 'Pesan tidak boleh kosong.',
        ]);

        DB::transaction(function () use ($data) {
            ComplaintResponse::create([
                'complaint_id' => $this->complaint->id,
                'resident_id' => auth()->user()->resident_id,
                'message' => $data['message'],
                'is_internal' => false,
            ]);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'create', 'module' => 'complaints',
                'subject_type' => Complaint::class, 'subject_id' => $this->complaint->id,
                'description' => 'Tanggapan warga pada pengaduan '.$this->complaint->ticket_number,
            ]);
        });

        $this->reset('message');
        $this->complaint->refresh();
        session()->flash('success', 'Tanggapan berhasil dikirim.');
    }

    /**
     * Warga menutup pengaduannya sendiri karena sudah selesai.
     */
    public function confirmResolved(): void
    {
        $this->authorize('view', $this->complaint);

        if (in_array($this->complaint->status, ['resolved', 'closed'], true)) {
            session()->flash('info', 'Pengaduan ini sudah selesai.');

            return;
        }

        DB::transaction(function () {
            $this->complaint->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_note' => $this->complaint->resolution_note ?? 'Dikonfirmasi selesai oleh warga.',
            ]);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'complaints',
                'subject_type' => Complaint::class, 'subject_id' => $this->complaint->id,
                'description' => 'Warga mengkonfirmasi selesai: '.$this->complaint->ticket_number,
                'new_values' => $this->complaint->fresh()->toArray(),
            ]);
        });

        $this->complaint->refresh();
        session()->flash('success', 'Pengaduan ditandai selesai. Terima kasih!');
    }

    #[Layout('layouts.resident', ['title' => 'Detail Pengaduan'])]
    public function render()
    {
        return view('livewire.resident.complaints.show', [
            'complaint' => $this->complaint->load(['house.block', 'responses.user', 'responses.resident', 'assignee']),
        ]);
    }
}
