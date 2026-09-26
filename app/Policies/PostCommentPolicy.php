<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;

class PostCommentPolicy
{
    public function create(User $user): bool
    {
        return $user->resident_id !== null;
    }

    public function update(User $user, PostComment $comment): bool
    {
        return $this->owns($user, $comment) || $user->hasPermission('manage-forum');
    }

    public function delete(User $user, PostComment $comment): bool
    {
        return $this->owns($user, $comment) || $user->hasPermission('manage-forum');
    }

    /**
     * Pemilik komentar ditentukan dari relasi resident milik user.
     */
    protected function owns(User $user, PostComment $comment): bool
    {
        return $user->resident_id !== null
            && (int) $comment->resident_id === (int) $user->resident_id;
    }
}
