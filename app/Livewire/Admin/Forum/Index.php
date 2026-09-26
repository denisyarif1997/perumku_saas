<?php

namespace App\Livewire\Admin\Forum;

use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Moderasi forum warga: sematkan/lepas postingan dan hapus konten melanggar.
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function togglePin(int $id): void
    {
        $post = Post::findOrFail($id);
        $this->authorize('pin', $post);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'forum',
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'description' => ($post->is_pinned ? 'Menyematkan' : 'Melepas sematan').' postingan forum: '.$post->title,
        ]);

        session()->flash('success', $post->is_pinned
            ? 'Postingan disematkan di atas forum.'
            : 'Sematan postingan dilepas.');
    }

    /**
     * Moderator menutup atau membuka kembali polling warga.
     */
    public function togglePoll(int $id): void
    {
        $post = Post::findOrFail($id);
        $this->authorize('closePoll', $post);

        $post->update(['poll_is_closed' => ! $post->poll_is_closed]);

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'forum',
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'description' => ($post->poll_is_closed ? 'Menutup' : 'Membuka kembali').' polling: '.$post->title,
        ]);

        session()->flash('success', $post->poll_is_closed
            ? 'Polling ditutup, warga tidak bisa memilih lagi.'
            : 'Polling dibuka kembali.');
    }

    public function deletePost(int $id): void
    {
        $post = Post::withCount('comments')->findOrFail($id);
        $this->authorize('delete', $post);

        DB::transaction(function () use ($post) {
            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $post->id,
                'description' => 'Moderator menghapus postingan forum: '.$post->title,
                'old_values' => $post->toArray(),
            ]);

            $post->comments()->delete();
            $post->deletePollVotes();
            $post->delete();
        });

        session()->flash('success', 'Postingan berhasil dihapus beserta komentarnya.');
    }

    public function deleteComment(int $id): void
    {
        $comment = PostComment::with('post')->findOrFail($id);
        $this->authorize('delete', $comment);

        DB::transaction(function () use ($comment) {
            ActivityLog::record([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $comment->post_id,
                'description' => 'Moderator menghapus komentar pada postingan: '.$comment->post?->title,
                'old_values' => $comment->toArray(),
            ]);

            $comment->delete();
        });

        session()->flash('success', 'Komentar berhasil dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Forum Warga'])]
    public function render()
    {
        return view('livewire.admin.forum.index', [
            'posts' => Post::query()
                ->with(['author', 'resident.houseResidents.house'])
                ->withCount('comments')
                ->withCount(['votes as poll_voters_count' => fn (Builder $q) => $q->selectRaw('count(distinct resident_id)')])
                ->when($this->search, fn ($q) => $q->where(function ($qq) {
                    $qq->where('title', 'like', "%{$this->search}%")
                        ->orWhere('body', 'like', "%{$this->search}%");
                }))
                ->inCategory($this->categoryFilter)
                ->pinnedFirst()
                ->paginate(10),
            'categories' => Post::categories(),
            'summary' => [
                'total' => Post::count(),
                'comments' => PostComment::count(),
                'today' => Post::whereDate('created_at', now()->toDateString())->count(),
            ],
            'recentComments' => PostComment::query()
                ->with(['post', 'author'])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
