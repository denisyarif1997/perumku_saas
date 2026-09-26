<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app: ada komentar baru pada postingan forum (untuk penulis postingan).
 */
class NewCommentOnPost extends Notification
{
    public function __construct(
        public Post $post,
        public string $commentBody,
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
            'post_id' => $this->post->id,
            'title' => $this->post->title,
            'excerpt' => Str::limit($this->commentBody, 140),
            'actor' => $this->actor,
        ];
    }
}
