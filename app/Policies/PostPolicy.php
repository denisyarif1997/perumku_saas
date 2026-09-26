<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resident_id !== null || $user->hasPermission('manage-forum');
    }

    public function view(User $user, Post $post): bool
    {
        // Forum terbuka: semua pengguna yang login boleh membaca postingan warga lain.
        return true;
    }

    public function create(User $user): bool
    {
        // Hanya warga yang punya data resident yang boleh membuat postingan.
        return $user->resident_id !== null;
    }

    public function update(User $user, Post $post): bool
    {
        return $this->owns($user, $post) || $user->hasPermission('manage-forum');
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->owns($user, $post) || $user->hasPermission('manage-forum');
    }

    public function pin(User $user, Post $post): bool
    {
        return $user->hasPermission('manage-forum');
    }

    /**
     * Hanya warga yang punya data warga boleh memilih polling. Postingan
     * non-polling tidak punya suara sama sekali.
     */
    public function vote(User $user, Post $post): bool
    {
        return $user->resident_id !== null && $post->is_poll;
    }

    /**
     * Menutup / membuka kembali polling: sama dengan hak moderasi, yaitu
     * pemilik polling atau pengelola forum.
     */
    public function closePoll(User $user, Post $post): bool
    {
        return $post->is_poll && ($this->owns($user, $post) || $user->hasPermission('manage-forum'));
    }

    /**
     * Pemilik postingan ditentukan dari relasi resident milik user.
     */
    protected function owns(User $user, Post $post): bool
    {
        return $user->resident_id !== null
            && (int) $post->resident_id === (int) $user->resident_id;
    }
}
