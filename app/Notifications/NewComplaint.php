<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app: ada laporan/pengaduan baru (untuk staf yang menangani laporan).
 */
class NewComplaint extends Notification
{
    public function __construct(
        public Complaint $complaint,
        public string $actor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'ticket_number' => $this->complaint->ticket_number,
            'title' => $this->complaint->title,
            'excerpt' => Str::limit($this->complaint->description, 140),
            'priority' => $this->complaint->priorityLabel(),
            'category' => $this->complaint->categoryLabel(),
            'actor' => $this->actor,
        ];
    }
}
