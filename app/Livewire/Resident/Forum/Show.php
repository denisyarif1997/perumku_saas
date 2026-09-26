<?php

namespace App\Livewire\Resident\Forum;

use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Notifications\NewCommentOnPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public Post $post;

    public string $body = '';

    /** Kunci pilihan polling yang sedang dicentang / dipilih. */
    public array $selectedOptions = [];

    /** Tampilkan form pilih ulang setelah warga sudah memilih. */
    public bool $editingVote = false;

    public function mount(Post $post): void
    {
        $this->authorize('view', $post);
        $this->post = $post;
        $this->selectedOptions = $post->pollSelectionsFor(auth()->user()?->resident_id);
    }

    /**
     * Warga memilih (atau mengganti) suaranya pada polling.
     */
    public function vote(): void
    {
        $this->authorize('vote', $this->post);

        $user = auth()->user();
        abort_if(! $user->resident_id, 403, 'Akun tidak terhubung dengan data warga.');

        if (! $this->post->isPollOpen()) {
            $this->resetValidation('selectedOptions');

            throw ValidationException::withMessages([
                'selectedOptions' => 'Polling ini sudah ditutup.',
            ]);
        }

        $options = $this->post->pollOptions();
        $isSingle = $this->post->poll_type !== 'multiple';

        $this->validate([
            'selectedOptions' => [
                'required',
                'array',
                $isSingle ? 'size:1' : 'min:1',
                'max:'.count($options),
            ],
            'selectedOptions.*' => ['string', Rule::in(array_keys($options))],
        ], [
            'selectedOptions.required' => 'Pilih salah satu jawaban dulu.',
            'selectedOptions.size' => 'Polling ini hanya boleh satu jawaban.',
            'selectedOptions.max' => 'Pilihan yang dipilih tidak tersedia.',
            'selectedOptions.*.in' => 'Pilihan yang dipilih tidak tersedia.',
        ]);

        $optionKeys = array_values(array_intersect($this->selectedOptions, array_keys($options)));

        DB::transaction(function () use ($user, $optionKeys) {
            // Ganti suara: suara lama warga ini dihapus agar tidak terhitung dua kali.
            $this->post->deletePollVotes((int) $user->resident_id);

            foreach ($optionKeys as $optionKey) {
                $this->post->votes()->create([
                    'resident_id' => $user->resident_id,
                    'user_id' => $user->id,
                    'option_key' => $optionKey,
                ]);
            }

            ActivityLog::record([
                'user_id' => $user->id,
                'action' => 'create',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $this->post->id,
                'description' => 'Memberikan suara pada polling: '.$this->post->title,
                'new_values' => ['selected_options' => $optionKeys],
            ]);
        });

        $this->selectedOptions = $optionKeys;
        $this->editingVote = false;
        $this->post->refresh();

        session()->flash('success', 'Suara Anda sudah tercatat.');
    }

    /**
     * Pilih atau lepas satu pilihan polling saat warga menekan radio/checkbox.
     *
     * Nilai dikirim lewat aksi, bukan wire:model, karena Livewire mengirim
     * nilai radio sebagai string (lihat getInputValue di livewire.esm.js) dan
     * checkbox hanya mengirim array bila nilai server sudah berupa array.
     * Keduanya tidak bisa masuk ke properti $selectedOptions bertipe array.
     *
     * Status centang digambar ulang dari $selectedOptions, jadi server yang
     * tetap menjadi sumber kebenaran.
     */
    public function selectOption(string $optionKey): void
    {
        // Hanya mengubah tampilan sesaat; penulisan suara tetap di vote().
        if (! $this->post->isPollOpen() || ! array_key_exists($optionKey, $this->post->pollOptions())) {
            return;
        }

        $selected = $this->selectedOptions;

        if ($this->post->poll_type === 'multiple') {
            $this->selectedOptions = in_array($optionKey, $selected, true)
                ? array_values(array_diff($selected, [$optionKey]))
                : array_values(array_unique([...$selected, $optionKey]));
        } else {
            $this->selectedOptions = [$optionKey];
        }

        $this->resetValidation('selectedOptions');
    }

    /**
     * Batal mengganti pilihan: kembalikan ke suara yang tersimpan.
     */
    public function cancelVoteEdit(): void
    {
        $this->selectedOptions = $this->post->pollSelectionsFor(auth()->user()?->resident_id);
        $this->editingVote = false;
        $this->resetValidation('selectedOptions');
    }

    /**
     * Tampilkan kembali form pilihan supaya warga bisa mengganti suaranya.
     */
    public function editVote(): void
    {
        $this->authorize('vote', $this->post);

        if (! $this->post->isPollOpen()) {
            session()->flash('error', 'Polling ini sudah ditutup.');
        }

        $this->editingVote = true;
    }

    /**
     * Menutup atau membuka kembali polling. Hanya pembuat polling dan
     * pengelola forum yang boleh memanggilnya.
     */
    public function closePoll(): void
    {
        $this->authorize('closePoll', $this->post);

        $this->post->update(['poll_is_closed' => ! $this->post->poll_is_closed]);
        $this->post->refresh();

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'forum',
            'subject_type' => Post::class,
            'subject_id' => $this->post->id,
            'description' => ($this->post->poll_is_closed ? 'Menutup' : 'Membuka kembali')
                .' polling: '.$this->post->title,
        ]);

        session()->flash('success', $this->post->poll_is_closed
            ? 'Polling sudah ditutup.'
            : 'Polling dibuka kembali.');
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
            $post->deletePollVotes();
            $post->delete();
        });

        session()->flash('success', 'Postingan berhasil dihapus.');

        return $this->redirectRoute('resident.forum.index', navigate: true);
    }

    #[Layout('layouts.resident', ['title' => 'Detail Postingan'])]
    public function render()
    {
        $post = $this->post->load(['author', 'resident.houseResidents.house', 'comments.author', 'comments.resident']);

        // Suara dan hasil polling hanya dihitung untuk yang berhak melihatnya,
        // agar warga yang belum memilih tidak bisa menebak angka suara lewat
        // markup halaman.
        $selections = $post->pollSelectionsFor(auth()->user()?->resident_id);
        $canViewResults = $post->canViewResults(auth()->user(), $selections);

        if ($canViewResults) {
            $post->load('votes');
        }

        return view('livewire.resident.forum.show', [
            'post' => $post,
            'commentCount' => $post->comments()->count(),
            'mySelections' => $selections,
            'canViewResults' => $canViewResults,
            'pollResults' => $canViewResults ? $post->pollResults() : [],
            'pollVotersCount' => $canViewResults ? $post->pollVotersCount() : 0,
            'canManagePoll' => auth()->user()->can('closePoll', $post),
            'canVote' => $post->isPollOpen() && auth()->user()->can('vote', $post),
        ]);
    }
}
