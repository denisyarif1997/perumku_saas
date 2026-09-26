<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app: pengelola menanggapi / mengubah status pengaduan (untuk warga pemilik).
 */
class ComplaintUpdated extends Notification
{
    public const KIND_REPLY = 'reply';

    public const KIND_STATUS = 'status';

    public function __construct(
        public Complaint $complaint,
        public string $kind,
        public string $message = '',
        public string $actor = '',
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
            'kind' => $this->kind,
            'message' => Str::limit($this->message, 140),
            'status' => $this->complaint->statusLabel(),
            'actor' => $this->actor,
        ];
    }
}
