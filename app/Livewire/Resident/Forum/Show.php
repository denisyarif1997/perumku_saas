<?php

namespace App\Livewire\Resident\Forum;

use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Notifications\NewCommentOnPost;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public Post $post;

    public string $body = '';

    public function mount(Post $post): void
    {
        $this->authorize('view', $post);
        $this->post = $post;
    }

    /**
     * Warga menambahkan komentar pada postingan warga lain.
     */
    public function addComment(): void
    {
        $this->authorize('create', PostComment::class);

        $user = auth()->user();
        abort_if(! $user->resident_id, 403, 'Akun tidak terhubung dengan data warga.');

        $data = $this->validate([
            'body' => ['required', 'string', 'max:1000'],
        ], [
            'body.required' => 'Komentar tidak boleh kosong.',
        ]);

        $comment = DB::transaction(function () use ($data, $user) {
            $comment = PostComment::create([
                'post_id' => $this->post->id,
                'resident_id' => $user->resident_id,
                'user_id' => $user->id,
                'body' => $data['body'],
            ]);

            ActivityLog::record([
                'user_id' => $user->id,
                'action' => 'create',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $this->post->id,
                'description' => 'Komentar baru pada postingan: '.$this->post->title,
                'new_values' => $comment->toArray(),
            ]);

            return $comment;
        });

        // Beri tahu penulis postingan bahwa ada komentar baru.
        if ($this->post->user_id && (int) $this->post->user_id !== (int) $user->id) {
            User::query()
                ->where('id', $this->post->user_id)
                ->where('status', 'active')
                ->first()
                ?->notify(new NewCommentOnPost($this->post, $comment->body, $user->name));
        }

        $this->reset('body');
        $this->post->refresh();
        session()->flash('success', 'Komentar berhasil dikirim.');
    }

    /**
     * Warga menghapus komentarnya sendiri. Moderator juga boleh menghapus.
     */
    public function deleteComment(int $id): void
    {
        $comment = PostComment::findOrFail($id);
        $this->authorize('delete', $comment);

        DB::transaction(function () use ($comment) {
            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $this->post->id,
                'description' => 'Menghapus komentar pada postingan: '.$this->post->title,
                'old_values' => $comment->toArray(),
            ]);

            $comment->delete();
        });

        $this->post->refresh();
        session()->flash('success', 'Komentar berhasil dihapus.');
    }

    /**
     * Warga menghapus postingannya sendiri lalu kembali ke daftar forum.
     */
    public function deletePost()
    {
        $this->authorize('delete', $this->post);

        $post = $this->post;

        DB::transaction(function () use ($post) {
            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $post->id,
                'description' => 'Menghapus postingan forum: '.$post->title,
                'old_values' => $post->toArray(),
            ]);

            $post->comments()->delete();
            $post->delete();
        });

        session()->flash('success', 'Postingan berhasil dihapus.');

        return $this->redirectRoute('resident.forum.index', navigate: true);
    }

    #[Layout('layouts.resident', ['title' => 'Detail Postingan'])]
    public function render()
    {
        return view('livewire.resident.forum.show', [
            'post' => $this->post->load(['author', 'resident.houseResidents.house', 'comments.author', 'comments.resident']),
            'commentCount' => $this->post->comments()->count(),
        ]);
    }
}
