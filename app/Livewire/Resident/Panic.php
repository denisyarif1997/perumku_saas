<?php

namespace App\Livewire\Resident;

use App\Services\EmergencyPanicService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Panic extends Component
{
    public string $reason = '';

    /**
     * Kirim alarm darurat baru. Duplikat dengan alasan yang sama
     * saat alarm masih aktif akan mengembalikan alarm tersebut tanpa dobel.
     * Alarm disiarkan ke sesama warga satu perumahan (push notifikasi in-app).
     */
    public function fire(EmergencyPanicService $service): void
    {
        $data = $this->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Keterangan darurat wajib diisi.',
        ]);

        $service->fire([
            'reason' => $data['reason'] ?? null,
        ]);

        $this->reset(['reason']);
        session()->flash('success', 'Alarm darurat berhasil dikirim.');
        $this->redirectRoute('resident.panic', navigate: true);
    }

    #[Layout('layouts.resident', ['title' => 'Alarm Darurat'])]
    public function render(EmergencyPanicService $service): View
    {
        return view('livewire.resident.panic', [
            'open' => $service->openByUser(auth()->id()),
            'count' => $service->countOpenByUser(auth()->id()),
        ]);
    }

    /**
     * Waktu relatif berbahasa Indonesia tanpa file translation (locale 'id),
     * mengikuti pola yang dipakai lonceng notifikasi.
     */
    public function formatTimeAgo(?CarbonInterface $datetime): string
    {
        if (! $datetime) {
            return '';
        }

        $seconds = max(0, now()->timestamp - $datetime->timestamp);

        return match (true) {
            $seconds < 60 => 'baru saja',
            $seconds < 3600 => ((int) floor($seconds / 60)).' menit lalu',
            $seconds < 86400 => ((int) floor($seconds / 3600)).' jam lalu',
            $seconds < 604800 => ((int) floor($seconds / 86400)).' hari lalu',
            default => $datetime->format('d M Y'),
        };
    }
}
