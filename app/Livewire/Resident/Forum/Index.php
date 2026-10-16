<?php

namespace App\Livewire\Resident\Forum;

use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewForumPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $body = '';

    public string $category = 'umum';

    /** Postingan ini dibuat sebagai polling, bukan diskusi biasa. */
    public bool $isPoll = false;

    /** Label pilihan polling yang sedang diketik (minimal dua, tanpa duplikat). */
    public array $pollChoices = ['', ''];

    /** "single" = satu pilihan, "multiple" = boleh lebih dari satu. */
    public string $pollType = 'single';

    /** Lama polling dibuka, dalam hari. "0" berarti tanpa batas waktu. */
    public string $pollDuration = '3';

    public function openForm(): void
    {
        $this->authorize('create', Post::class);
        $this->showForm = true;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->reset(['body']);
        $this->category = 'umum';
        $this->isPoll = false;
        $this->pollChoices = ['', ''];
        $this->pollType = 'single';
        $this->pollDuration = '3';
        $this->resetValidation();
    }

    /**
     * Menghapus polling yang sedang disusun tanpa menutup composer.
     */
    public function disablePoll(): void
    {
        $this->isPoll = false;
        $this->resetValidation();
    }

    public function addPollChoice(): void
    {
        if (! $this->isPoll || count($this->pollChoices) >= Post::POLL_MAX_OPTIONS) {
            return;
        }

        $this->pollChoices[] = '';
    }

    public function removePollChoice(int $index): void
    {
        // Dua pilihan terakhir tidak boleh dihapus: polling butuh minimal dua.
        if (count($this->pollChoices) <= 2 || ! array_key_exists($index, $this->pollChoices)) {
            return;
        }

        unset($this->pollChoices[$index]);
        $this->pollChoices = array_values($this->pollChoices);
    }

    /**
     * Daftar pilihan polling yang sudah dirapikan: tanpa spasi berlebih dan
     * tanpa label ganda.
     *
     * @return array<int, string>
     */
    protected function cleanedPollChoices(): array
    {
        return collect($this->pollChoices)
            ->map(fn ($choice) => trim((string) $choice))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{is_poll: bool, poll_type: string, poll_options: ?array, poll_closes_at: ?string, poll_is_closed: bool}
     */
    protected function pollAttributes(): array
    {
        return [
            'is_poll' => $this->isPoll,
            'poll_type' => $this->pollType === 'multiple' ? 'multiple' : 'single',
            'poll_options' => $this->isPoll
                ? collect($this->cleanedPollChoices())
                    ->values()
                    ->map(fn (string $label, int $index) => ['key' => 'o'.($index + 1), 'label' => $label])
                    ->all()
                : null,
            'poll_closes_at' => $this->pollDuration === '0' ? null : now()->addDays((int) $this->pollDuration),
            'poll_is_closed' => false,
        ];
    }

    /**
     * Warga membuat status baru. Judul tidak lagi diisi manual:
     * diambil otomatis dari 8 kata pertama isi agar data lama
     * (admin/notifikasi/pencarian yang memakai title) tetap jalan.
     */
    public function submit(): void
    {
        $this->authorize('create', Post::class);

        $user = auth()->user();
        abort_if(! $user->resident_id, 403, 'Akun tidak terhubung dengan data warga.');

        $data = $this->validate([
            'body' => ['required', 'string', 'max:3000'],
            'pollChoices' => $this->isPoll
                ? ['array', 'min:2', 'max:'.Post::POLL_MAX_OPTIONS]
                : ['nullable'],
            'pollChoices.*' => [$this->isPoll ? 'required' : 'nullable', 'string', 'max:100'],
        ], [
            'body.required' => 'Tulis dulu statusnya.',
            'pollChoices.min' => 'Polling minimal memiliki dua pilihan.',
            'pollChoices.max' => 'Pilihan polling maksimal '.Post::POLL_MAX_OPTIONS.'.',
            'pollChoices.*.required' => 'Pilihan polling tidak boleh kosong.',
            'pollChoices.*.max' => 'Setiap pilihan polling maksimal 100 karakter.',
        ]);

        // Dua pilihan identik tetap tidak sah karena hanya menyisakan satu
        // jawaban untuk dipilih warga.
        if ($this->isPoll && count($this->cleanedPollChoices()) < 2) {
            throw ValidationException::withMessages([
                'pollChoices' => 'Polling minimal memiliki dua pilihan yang berbeda.',
            ]);
        }

        $residentId = (int) $user->resident_id;
        $houseId = DB::table('house_residents')
            ->where('resident_id', $residentId)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->value('house_id');

        $estateId = $houseId
            ? DB::table('houses')->where('id', $houseId)->value('housing_estate_id')
            : null;

        $post = DB::transaction(function () use ($data, $residentId, $estateId, $user) {
            $body = trim($data['body']);
            $title = implode(' ', array_slice(preg_split('/\s+/', $body) ?: [], 0, 8));
            $title = mb_substr($title !== '' ? $title : 'Status warga', 0, 150);

            $post = Post::create([
                'housing_estate_id' => $estateId,
                'resident_id' => $residentId,
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'category' => 'umum',
                'is_pinned' => false,
                ...$this->pollAttributes(),
            ]);

            ActivityLog::record([
                'user_id' => $user->id,
                'action' => 'create',
                'module' => 'forum',
                'subject_type' => Post::class,
                'subject_id' => $post->id,
                'description' => ($post->is_poll ? 'Polling forum baru: ' : 'Postingan forum baru: ').$post->title,
                'new_values' => $post->toArray(),
            ]);

            return $post;
        });

        // Beri tahu staf pengelola forum + seluruh warga pada estate yang sama
        // (duplikat antar peran dicegah unique('id'), penulis postingan dikecualikan).
        User::staffWithPermission('manage-forum')
            ->get()
            ->concat(User::residentUsersOfEstate($estateId !== null ? (int) $estateId : null)->get())
            ->unique('id')
            ->where('id', '!=', $user->id)
            ->each(fn (User $recipient) => $recipient->notify(new NewForumPost($post, $user->name)));

        $this->closeForm();
        session()->flash('success', $post->is_poll
            ? 'Polling berhasil dibagikan ke warga.'
            : 'Postingan berhasil dibagikan ke warga.');
        $this->redirectRoute('resident.forum.show', $post, navigate: true);
    }

    /**
     * Warga menghapus postingannya sendiri. Moderator juga boleh menghapus.
     */
    public function delete(int $id): void
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
                'description' => 'Menghapus postingan forum: '.$post->title,
                'old_values' => $post->toArray(),
            ]);

            // Komentar dan suara polling ikut terhapus agar tidak ada sisa yatim.
            $post->comments()->delete();
            $post->deletePollVotes();
            $post->delete();
        });

        session()->flash('success', 'Postingan berhasil dihapus.');
        $this->resetPage();
    }

    #[Layout('layouts.resident', ['title' => 'Forum Warga'])]
    public function render()
    {
        $query = Post::query()
            ->with(['author', 'resident.houseResidents.house'])
            ->withCount('comments')
            ->withCount(['votes as poll_voters_count' => fn (Builder $q) => $q->selectRaw('count(distinct resident_id)')])
            ->pinnedFirst();

        return view('livewire.resident.forum.index', [
            'posts' => $query->paginate(10),
            'maxPollChoices' => Post::POLL_MAX_OPTIONS,
        ]);
    }
}
