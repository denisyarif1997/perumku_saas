<?php

namespace App\Livewire\Admin\Info;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\User;
use App\Notifications\ComplaintUpdated;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ComplaintDetail extends Component
{
    public Complaint $complaint;

    public string $response_message = '';

    public bool $is_internal = false;

    public string $status = '';

    public string $assigned_to = '';

    public string $resolution_note = '';

    public function mount(Complaint $complaint): void
    {
        abort_unless(auth()->user()->hasPermission('manage-complaint'), 403);

        $this->complaint = $complaint;
        $this->status = $complaint->status;
        $this->assigned_to = $complaint->assigned_to ? (string) $complaint->assigned_to : '';
        $this->resolution_note = $complaint->resolution_note ?? '';
    }

    public function saveResponse(): void
    {
        $this->authorize('respond', $this->complaint);

        $data = $this->validate([
            'response_message' => ['required', 'string', 'max:1000'],
            'is_internal' => ['boolean'],
        ], [
            'response_message.required' => 'Pesan tanggapan wajib diisi.',
        ]);

        DB::transaction(function () use ($data) {
            ComplaintResponse::create([
                'complaint_id' => $this->complaint->id,
                'user_id' => auth()->id(),
                'message' => $data['response_message'],
                'is_internal' => (bool) ($data['is_internal'] ?? false),
            ]);

            // Tanggapan publik otomatis mengalihkan status menjadi diproses.
            if ($this->complaint->status === 'open' && ! $data['is_internal']) {
                $this->complaint->update(['status' => 'in_progress']);
                $this->status = 'in_progress';
            }

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'create', 'module' => 'complaints',
                'subject_type' => Complaint::class, 'subject_id' => $this->complaint->id,
                'description' => 'Tanggapan staf pada pengaduan '.$this->complaint->ticket_number,
            ]);
        });

        // Tanggapan publik dikirim sebagai notifikasi ke warga pemilik pengaduan.
        if (! $data['is_internal']) {
            $this->notifyResident('reply', $data['response_message']);
        }

        $this->reset('response_message', 'is_internal');
        $this->complaint->refresh();
        session()->flash('success', 'Tanggapan berhasil dikirim.');
    }

    public function updateStatus(): void
    {
        $this->authorize('update', $this->complaint);

        $data = $this->validate([
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $old = $this->complaint->toArray();

        DB::transaction(function () use ($data, $old) {
            $payload = [
                'status' => $data['status'],
                'assigned_to' => $data['assigned_to'] ?: null,
                'resolution_note' => $data['resolution_note'] ?: null,
            ];

            if (in_array($data['status'], ['resolved', 'closed'], true) && ! $this->complaint->resolved_at) {
                $payload['resolved_at'] = now();
                $payload['resolved_by'] = auth()->id();
            }

            $this->complaint->update($payload);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'complaints',
                'subject_type' => Complaint::class, 'subject_id' => $this->complaint->id,
                'description' => 'Memperbarui pengaduan '.$this->complaint->ticket_number
                    .' — status: '.$data['status'],
                'old_values' => $old, 'new_values' => $this->complaint->fresh()->toArray(),
            ]);
        });

        $this->complaint->refresh();
        $this->assigned_to = $this->complaint->assigned_to ? (string) $this->complaint->assigned_to : '';

        // Beri tahu warga bila status pengaduannya berubah.
        if (($old['status'] ?? null) !== $this->complaint->status) {
            $this->notifyResident('status', (string) ($this->complaint->resolution_note ?? ''));
        }

        session()->flash('success', 'Pengaduan berhasil diperbarui.');
    }

    /**
     * Kirim notifikasi in-app ke seluruh akun warga pemilik pengaduan.
     */
    private function notifyResident(string $kind, string $message = ''): void
    {
        if (! $this->complaint->resident_id) {
            return;
        }

        User::residentUsers((int) $this->complaint->resident_id)
            ->get()
            ->each(fn (User $residentUser) => $residentUser->notify(new ComplaintUpdated(
                $this->complaint,
                $kind,
                $message,
                auth()->user()?->name ?? 'Pengelola',
            )));
    }

    #[Layout('layouts.admin', ['title' => 'Detail Pengaduan'])]
    public function render()
    {
        return view('livewire.admin.info.complaint-detail', [
            'complaint' => $this->complaint->load(['house.block', 'resident', 'responses.user', 'responses.resident', 'assignee', 'resolver']),
            'staff' => User::query()
                ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'resident'))
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
