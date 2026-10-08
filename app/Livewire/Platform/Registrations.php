<?php

namespace App\Livewire\Platform;

use App\Models\EstateRegistration;
use App\Services\EstateRegistrationService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Menu superadmin: daftar pengajuan perumahan baru + approve/reject.
 * Approve meng-inject staging ke tabel produksi via service (1 transaksi).
 */
class Registrations extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';

    public string $search = '';

    public ?int $rejectId = null;

    public string $rejectReason = '';

    #[Layout('layouts.admin', ['title' => 'Verifikasi Perumahan Baru'])]
    public function render()
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $rows = EstateRegistration::query()
            ->with(['user', 'decider'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $w->where('estate_name', 'like', '%'.$this->search.'%')
                    ->orWhere('estate_code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->latest('id')
            ->paginate(15);

        return view('livewire.platform.registrations', [
            'rows' => $rows,
            'pendingCount' => EstateRegistration::query()->where('status', 'pending')->count(),
        ]);
    }

    public function approve(int $id, EstateRegistrationService $service): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $registration = EstateRegistration::findOrFail($id);

        try {
            $result = $service->approve($registration, auth()->user());
            session()->flash('status', "Perumahan {$result['estate']->name} aktif. {$result['user']->name} kini admin.");
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function startReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectId = null;
        $this->rejectReason = '';
    }

    public function reject(int $id, EstateRegistrationService $service): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $this->validate(['rejectReason' => ['nullable', 'string', 'max:500']]);

        $registration = EstateRegistration::findOrFail($id);

        try {
            $service->reject($registration, auth()->user(), $this->rejectReason ?: null);
            session()->flash('status', 'Pengajuan ditolak.');
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->cancelReject();
    }
}
