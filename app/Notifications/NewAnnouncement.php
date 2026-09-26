<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app: pengumuman baru diterbitkan (untuk warga pada estate terkait).
 */
class NewAnnouncement extends Notification
{
    public function __construct(
        public Announcement $announcement,
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
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'excerpt' => Str::limit($this->announcement->content, 140),
            'category' => $this->announcement->categoryLabel(),
            'priority' => $this->announcement->priorityLabel(),
            'actor' => $this->actor,
        ];
    }
}
