<?php

namespace App\Livewire;

use App\Notifications\EmergencyPanicNotification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pengawas alarm darurat: di-poll tiap 5 detik dan mengekspos alarm
 * yang belum dibaca sebagai JSON untuk JS (sirene + banner + push).
 *
 * Query disengaja ringan (maks 5 baris, 30 menit terakhir) agar polling
 * cepat tidak membebani DB. Alarm lama tetap terlihat di lonceng
 * notifikasi, hanya saja tidak membunyikan sirene ulang.
 */
class PanicAlertWatcher extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $alerts = [];

        if ($user) {
            $alerts = $user->unreadNotifications()
                ->where('type', EmergencyPanicNotification::class)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Alarm Darurat',
                    'excerpt' => $notification->data['excerpt'] ?? '',
                    'actor' => $notification->data['actor'] ?? '',
                    'url' => $user->hasPermission('manage-emergency')
                        ? route('admin.dashboard')
                        : route('resident.panic'),
                ])
                ->all();
        }

        return view('livewire.panic-alert-watcher', [
            'alerts' => $alerts,
        ]);
    }
}
