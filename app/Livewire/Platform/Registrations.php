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

    public ?string $approvedWaLink = null;

    public string $approvedWaMessage = '';

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

            $this->approvedWaLink = $this->approvalWaLink($registration->fresh(), $result['estate']->name, $result['user']->name);
            $this->approvedWaMessage = $this->approvalWaMessage($registration->fresh(), $result['estate']->name, $result['user']->name);
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function dismissApprovalWa(): void
    {
        $this->approvedWaLink = null;
        $this->approvedWaMessage = '';
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

    /**
     * Tautan WhatsApp berisi kabar persetujuan + ajakan login.
     * Nomor pemohon dinormalisasi ke format internasional (62...).
     */
    public function approvalWaLink(EstateRegistration $registration, ?string $estateName = null, ?string $adminName = null): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $registration->phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits.'?text='.urlencode($this->approvalWaMessage($registration, $estateName, $adminName));
    }

    public function approvalWaMessage(EstateRegistration $registration, ?string $estateName = null, ?string $adminName = null): string
    {
        $name = $adminName ?? $registration->name;
        $estate = $estateName ?? $registration->estate_name;
        $loginUrl = route('login');

        return "Halo {$name}, pengajuan perumahan {$estate} di Perumku sudah DISETUJUI. Silakan login di {$loginUrl} untuk mengelola perumahan Anda. Terima kasih.";
    }
}
